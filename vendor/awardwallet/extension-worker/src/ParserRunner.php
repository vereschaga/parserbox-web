<?php

namespace AwardWallet\ExtensionWorker;

use AwardWallet\Schema\Parser\Component\Master;
use Psr\Log\LoggerInterface;

class ParserRunner
{

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function loginWithLoginId(LoginWithIdInterface $parser, Client $client, LoginWithLoginIdRequest $request) : LoginWithIdResult
    {
        $credentials = $request->getCredentials();
        $activeTab = $request->getActiveTab();
        $this->logLoginParams($parser, $credentials, $request->getLoginId());
        $loginId = $this->checkLoginIdValidity($request->getLoginId(), $credentials->getLogin());

        $options = new AccountOptions($credentials->getLogin(), $credentials->getLogin2(), $credentials->getLogin3(), false);
        try {
            $url = $parser->getStartingUrl($options);
        }
        catch (\Throwable $exception) {
            throw new ParserException($exception->getMessage(), 0, $exception);
        }
        $this->logger->info('starting url is: ' . $url);
        if ($activeTab === null) {
            $activeTab = $this->getActiveTab($parser, $options);
        }

        $newTabUrl = $url;
        if ($request->getAffiliateLink()) {
            $newTabUrl = $request->getAffiliateLink();
            $this->logger->info("using affiliate link: " . $request->getAffiliateLink());
        }

        $this->logger->info('new tab url is: ' . $newTabUrl);
        $tab = $client->newTab($newTabUrl, $activeTab);
        $this->logger->info('IsLoggedIn', ['Header' => 2]);
        try {
            $tab->showMessage(''); // show default message 'This tab is currently controlled by AwardWallet.'
            if ($request->getAffiliateLink()) {
                $this->logger->info("proceeding to start url: " . $url);
                $tab->getUrl($url);
            }
            $isLoggedIn = $parser->isLoggedIn($tab);
            $this->logger->info('isLoggedIn: ' . json_encode($isLoggedIn));
            if ($isLoggedIn && $loginId !== '') {
                $this->logger->info('running getLoginId to compare with ' . $loginId);
                $pageLoginId = strtolower(trim($parser->getLoginId($tab)));
                $this->logger->info('page loginId: ' . $pageLoginId);
                if ($loginId === $pageLoginId) {
                    $this->logger->info("already logged in and loginId matches");

                    return new LoginWithIdResult(new LoginResult(true), $tab);
                }
            }

            if ($isLoggedIn) {
                $this->logger->info('Logout', ['Header' => 2]);
                $this->logger->info('logging off, because loginId "' . $loginId . '" mismatch or empty');
                $parser->logout($tab);
                $tab->gotoUrl($url);

                $this->logger->info('IsLoggedIn', ['Header' => 2]);
                if ($parser->isLoggedIn($tab)) {
                    throw new ParserException("Failed to logoff");
                }
            }

            $this->logger->info('Login', ['Header' => 2]);
            $loginResult = $parser->login($tab, $credentials);
            $this->logger->info('login result: ' . json_encode($loginResult));
            if ($loginResult->success) {
                $this->logger->info('logged in, calling getLoginId');
                $pageLoginId = strtolower(trim($parser->getLoginId($tab)));
                $this->logger->info('loginId: ' . $pageLoginId);
                $client->saveLoginId($pageLoginId, $credentials->getLogin());

                return new LoginWithIdResult($loginResult, $tab);
            }

            $tab->logPageState();

            return new LoginWithIdResult($loginResult, $tab);
        }
        catch (\CheckException $exception) {
            $tab->logPageState();

            return new LoginWithIdResult(new LoginResult(false, $exception->getMessage(), null, $exception->getCode()), $tab);
        }
        catch (CommunicationException $exception) {
            $this->logger->notice($exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception));

            return new LoginWithIdResult(new LoginResult(false, $exception->getMessage(), null, ACCOUNT_ENGINE_ERROR), $tab);
        }
        catch (ElementNotFoundException $exception) {
            $this->logger->notice($exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception));
            $tab->logPageState();

            return new LoginWithIdResult(new LoginResult(false, $exception->getMessage(), null, ACCOUNT_ENGINE_ERROR), $tab);
        }
        catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception));
            $tab->logPageState();

            return new LoginWithIdResult(new LoginResult(false, $exception->getMessage(), null, ACCOUNT_ENGINE_ERROR), $tab);
        }
    }

    public function continueLogin(ContinueLoginInterface $parser, Client $client, Tab $tab, Credentials $credentials, string $loginId, FileLogger $fileLogger) : LoginWithIdResult
    {
        $this->logLoginParams($parser, $credentials, $loginId);
        $loginId = $this->checkLoginIdValidity($loginId, $credentials->getLogin());

        try {
            $this->logger->info('continueLogin', ['Header' => 2]);
            $loginResult = $parser->continueLogin($tab, $credentials);
            $this->logger->info('login result: ' . json_encode($loginResult));
            if ($loginResult->success) {
                $this->logger->info('logged in, calling getLoginId');
                $pageLoginId = strtolower(trim($parser->getLoginId($tab)));
                $this->logger->info('loginId: ' . $pageLoginId);
                $client->saveLoginId($pageLoginId, $credentials->getLogin());

                return new LoginWithIdResult($loginResult, $tab);
            }

            return new LoginWithIdResult($loginResult, $tab);
        }
        catch (\CheckException $exception) {
            $tab->logPageState();

            return new LoginWithIdResult(new LoginResult(false, $exception->getMessage(), null, $exception->getCode()), $tab);
        }
        catch (CommunicationException $exception) {
            $this->logger->notice($exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception));

            return new LoginWithIdResult(new LoginResult(false, $exception->getMessage(), null, ACCOUNT_ENGINE_ERROR), $tab);
        }
        catch (ElementNotFoundException $exception) {
            $this->logger->notice($exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception));
            $tab->logPageState();

            return new LoginWithIdResult(new LoginResult(false, $exception->getMessage(), null, ACCOUNT_ENGINE_ERROR), $tab);
        }
        catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception));
            $tab->logPageState();

            return new LoginWithIdResult(new LoginResult(false, $exception->getMessage(), null, ACCOUNT_ENGINE_ERROR), $tab);
        }
    }

    public function loginWithConfNo(LoginWithConfNoInterface $parser, Client $client, array $confNoFields, ConfNoOptions $confNoOptions, ?string $affiliateLink) : ParserRunnerLoginWithConfNoResult
    {
        $this->logger->info('Login With Conf No Parameters', ['Header' => 2]);
        $this->logger->info("Provider engine: " . get_class($parser));
        $this->logger->info("Answers on enter: " . json_encode($confNoFields));

        try {
            $startingUrl = $parser->getLoginWithConfNoStartingUrl($confNoFields, $confNoOptions);
        }
        catch (\Throwable $exception) {
            throw new ParserException($exception->getMessage(), 0, $exception);
        }
        $this->logger->info('starting url is: ' . $startingUrl);

        $newTabUrl = $startingUrl;
        if ($affiliateLink) {
            $this->logger->info("using affiliate link: $affiliateLink");
            $newTabUrl = $affiliateLink;
        }

        $tab = $client->newTab($newTabUrl, true);
        try {
            $this->logger->info('LoginWithConfNo', ['Header' => 2]);
            $tab->showMessage(''); // show default message 'This tab is currently controlled by AwardWallet.'
            if ($affiliateLink) {
                $this->logger->info("navigating from affiliate link to starting url " . $startingUrl);
                $tab->gotoUrl($startingUrl);
            }
            $result = $parser->loginWithConfNo($tab, $confNoFields, $confNoOptions);
            if ($result->isSuccess()) {
                $this->logger->info('LoginWithConfNo was successful');
            } else {
                $this->logger->info("LoginWithConfNo returned error: $result->errorMessage");
            }

            return new ParserRunnerLoginWithConfNoResult($result->errorMessage, $tab);
        }
        catch (\CheckException $exception) {
            if ($exception->getPrevious() === null) {
                $exception = $exception->getPrevious();
            }

            $message = "CheckException: " . $exception->getMessage() . " at " . $exception->getFile() . ':' . $exception->getLine();
            $this->logger->notice($message);
            $tab->logPageState();

            return new ParserRunnerLoginWithConfNoResult($exception->getMessage(), $tab, $message);
        }
        catch (ElementNotFoundException $exception) {
            $message = $exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception);
            $this->logger->notice($message);
            $tab->logPageState();

            return new ParserRunnerLoginWithConfNoResult("Unknown error", $tab, $message);
        }
        catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception));
            $tab->logPageState();

            return new ParserRunnerLoginWithConfNoResult("Unknown error", $tab);
        }
    }

    public function retrieveByConfNo(RetrieveByConfNoInterface $parser, Tab $tab, Master $master, array $confNoFields, ConfNoOptions $confNoOptions, FileLogger $fileLogger) : void
    {
        try {
            $this->logger->info('retrieveByConfNo', ['Header' => 2]);
            $parser->retrieveByConfNo($tab, $master, $confNoFields, $confNoOptions);
            $this->logger->info('retrieveByConfNo Result', ['Header' => 2]);
            $this->logMaster($master);
        }
        catch (\CheckException $exception) {
            $tab->logPageState();
            throw $exception;
        }
        catch (ElementNotFoundException $exception) {
            $message = $exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception);
            $this->logger->notice($message);
            $tab->logPageState();
            throw new \CheckException($message, ACCOUNT_ENGINE_ERROR, $exception);
        }
        catch (\Throwable $exception) {
            $message = $exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception);
            $this->logger->notice($message);
            $tab->logPageState();
            throw new \CheckException($message, ACCOUNT_ENGINE_ERROR, $exception);
        }
    }
    /**
     * @param ParseInterface|ParseAllInterface $parser
     */
    public function parseAll($parser, Tab $tab, Master $master, ParseAllOptions $parseOptions, FileLogger $fileLogger)
    {
        try {
            if ($parser instanceof ParseAllInterface && $parser instanceof ParseInterface) {
                throw new ParserException("Parser should implement ParseAllInterface or ParseInterface, not both");
            }

            if ($parser instanceof ParseAllInterface && $parser instanceof ParseHistoryInterface) {
                throw new ParserException("Parser should implement ParseAllInterface or ParseHistoryInterface, not both");
            }

            if ($parser instanceof ParseAllInterface && $parser instanceof ParseItinerariesInterface) {
                throw new ParserException("Parser should implement ParseAllInterface or ParseItinerariesInterface, not both");
            }

            $credentials = $parseOptions->getCredentials();
            $accountOptions = new AccountOptions($credentials->getLogin(), $credentials->getLogin2(), $credentials->getLogin3(), false);

            if ($parser instanceof ParseAllInterface) {
                $this->logger->info('Parse All', ['Header' => 2]);
                $parser->parseAll($tab, $master, $accountOptions, $parseOptions->getParseHistoryOptions(), $parseOptions->getParseItinerariesOptions());

                return;
            }

            if (!$parser instanceof ParseInterface) {
                throw new ParserException("Parser should implement ParseAllInterface or ParseInterface");
            }

            $this->logger->info('Parse', ['Header' => 2]);
            $parser->parse($tab, $master, $accountOptions);
            $this->logger->info('Account Check Result', ['Header' => 2]);

            if ($parseOptions->getParseItinerariesOptions() && $parser instanceof ParseItinerariesInterface) {
                $this->logger->info('Parse Itineraries', ['Header' => 2]);
                $parser->parseItineraries($tab, $master, $accountOptions, $parseOptions->getParseItinerariesOptions());
            }

            if ($parseOptions->getParseHistoryOptions() && $parser instanceof ParseHistoryInterface) {
                $this->logger->info('Parse History', ['Header' => 2]);
                $parser->parseHistory($tab, $master, $accountOptions, $parseOptions->getParseHistoryOptions());
            }

            $this->logMaster($master);
        }
        catch (\CheckException $exception) {
            $tab->logPageState();
            throw $exception;
        }
        catch (ElementNotFoundException $exception) {
            $message = $exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception);
            $this->logger->notice($message);
            $tab->logPageState();
            throw new \CheckException($message, ACCOUNT_ENGINE_ERROR, $exception);
        }
        catch (\Throwable $exception) {
            $message = $exception->getMessage() . " at " . $this->getParserErrorLocation(get_class($parser), $exception);
            $this->logger->notice($message);
            $tab->logPageState();
            throw new \CheckException($message, ACCOUNT_ENGINE_ERROR, $exception);
        }
    }

    private function checkLoginIdValidity(string $loginId, string $login) : string
    {
        if ($loginId === '') {
            return '';
        }

        $p = strpos($loginId, ':');
        if ($p === false) {
            $this->logger->info("loginId does not contain ':', discarding");

            return '';
        }

        $cleanLoginId = substr($loginId, 0, $p);
        $savedLogin = substr($loginId, $p + 1);

        if (strcmp($savedLogin, $login) !== 0) {
            $this->logger->info("loginId login ($savedLogin) does not match passed login ($login), discarding");

            return '';
        }

        return $cleanLoginId;
    }

    private function getParserErrorLocation(string $parserClass, \Throwable $exception) : string
    {
        $reflection = new \ReflectionClass($parserClass);

         if ($exception->getFile() === $reflection->getFileName()) {
            return $exception->getFile() . ':' . $exception->getLine();
        }

        $lastFrame = null;
        foreach ($exception->getTrace() as $frame) {
            if (isset($frame['class']) && $frame['class'] === $parserClass && $lastFrame) {
                return $lastFrame['file'] . ':' . $lastFrame['line'];
            }

            $lastFrame = $frame;
        }

        return $exception->getFile() . ':' . $exception->getLine();
    }

    private function getActiveTab(LoginWithIdInterface $parser, AccountOptions $options)
    {
        if (!($parser instanceof ActiveTabInterface)) {
            return false;
        }

        try {
            return $parser->isActiveTab($options);
        } catch (\Throwable $exception) {
            throw new ParserException($exception->getMessage(), 0, $exception);
        }
    }

    private function logLoginParams($parser, Credentials $credentials, string $loginId) : void
    {
        $this->logger->info('Account Check Parameters', ['Header' => 2]);
        $this->logger->info("Provider engine: " . get_class($parser));
        $this->logger->info('Login: ' . $credentials->getLogin());
        $this->logger->info('Login2: ' . $credentials->getLogin2());
        $this->logger->info('Login3: ' . $credentials->getLogin3());
        $this->logger->info("Answers on enter: " . json_encode($credentials->getAnswers()));
        $this->logger->info('LoginId: ' . $loginId);
    }

    private function logMaster(Master $master) : void
    {
        $this->logger->info(self::formatJsonData($master->toArray()), ['pre' => true]);
    }

    public static function formatJsonData($data) : string
    {
        $text = json_encode($data, JSON_PRETTY_PRINT);
        $text = preg_replace_callback('#"\w+": (\d{10})\,#ims', fn($matches) => $matches[0] . ' // ' . date("Y-m-d H:i:s", $matches[1]), $text);

        return $text;
    }

}