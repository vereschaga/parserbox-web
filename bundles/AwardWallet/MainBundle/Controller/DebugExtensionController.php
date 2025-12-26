<?php

namespace AwardWallet\MainBundle\Controller;

use AwardWallet\Common\Parsing\Exception\ErrorFormatter;
use AwardWallet\Common\Parsing\Web\Captcha\CaptchaServices;
use AwardWallet\Common\Parsing\Web\Captcha\Context;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\CentrifugeLogHandler;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\ConfNoOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\DebugWatchdogControl;
use AwardWallet\ExtensionWorker\ExtensionResponse;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithConfNoInterface;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\LoginWithIdResult;
use AwardWallet\ExtensionWorker\LoginWithLoginIdRequest;
use AwardWallet\ExtensionWorker\ParseAllOptions;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\ParserContext;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\ParserLogger;
use AwardWallet\ExtensionWorker\ParserRunner;
use AwardWallet\ExtensionWorker\ProviderInfoFactory;
use AwardWallet\ExtensionWorker\ResponseReceiver;
use AwardWallet\ExtensionWorker\RetrieveByConfNoInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use AwardWallet\ExtensionWorker\SeleniumSessionManager;
use AwardWallet\ExtensionWorker\ServerCheckOptionsFactory;
use AwardWallet\ExtensionWorker\ServerConfigFactory;
use AwardWallet\ExtensionWorker\SessionManager;
use AwardWallet\ExtensionWorker\State;
use AwardWallet\MainBundle\Service\DummyNotificationSender;
use AwardWallet\Schema\Parser\Component\Master;
use Doctrine\DBAL\Connection;
use Monolog\Logger;
use phpcent\Client;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DebugExtensionController extends Controller
{

    const NO_SESSION_REQUEST_PREFIX = 'req_';

    private iterable $captchaProviders;

    public function __construct(iterable $captchaProviders)
    {
        $this->captchaProviders = $captchaProviders;
    }

    /**
     * @Route("/admin/debug-extension", methods={"GET", "POST"})
     */
    public function debugExtensionAction(Request $request, SessionManager $sessionManager, \Memcached $memcached, Client $centrifuge, ServerConfigFactory $serverConfigFactory, Connection $connection)
    {
        $form = $this->createFormBuilder()
            ->add('providerCode', TextType::class)
            ->add('method', ChoiceType::class, ['choices' => ['autologin' => 'autologin', 'autologin_with_conf_no' => 'autologin_with_conf_no', 'retrieve_by_conf_no' => 'retrieve_by_conf_no', 'parse' => 'parse']])
            ->add('loglevel', ChoiceType::class, ['choices' => array_combine(array_flip(Logger::getLevels()), array_flip(Logger::getLevels())), 'data' => 'DEBUG'])
            ->add('login', TextType::class, ['required' => false])
            ->add('login2', TextType::class, ['required' => false])
            ->add('login3', TextType::class, ['required' => false])
            ->add('password', PasswordType::class, ['always_empty' => false, 'required' => false])
            ->add('answers', TextareaType::class, ['attr' => ['rows' => 2], 'required' => false])
            ->add('confNoFields', TextareaType::class, ['attr' => ['rows' => 3], 'required' => false])
            ->add('loginId', TextType::class, ['required' => false])
            ->add('parseItineraries', CheckboxType::class, ['required' => false])
            ->add('parsePastItineraries', CheckboxType::class, ['required' => false])
            ->add('parseHistory', CheckboxType::class, ['required' => false])
            ->add('historyStartDate', DateTimeType::class, ['required' => false])
            ->add('keepTabOpen', CheckboxType::class, ['required' => false, 'data' => true])
            ->add('serverCheck', CheckboxType::class, ['required' => false, 'data' => false])
            ->add('runInSingleTab', CheckboxType::class, ['required' => false, 'data' => true, 'label' => 'Run In Single Tab'])
            ->add('background', CheckboxType::class, ['required' => false, 'data' => false, 'label' => 'Background check'])
            ->add('mailboxConnected', CheckboxType::class, ['required' => false, 'data' => false, 'label' => 'Mailbox connected'])
            ->add('useClickUrl', CheckboxType::class, ['required' => false, 'data' => false, 'label' => 'use Click URL'])
            ->add('clickUrl', TextType::class, ['required' => false, 'label' => 'override Click URL'])
            ->add('save', SubmitType::class, ['label' => 'Run'])
            ->getForm();

        $requestId = null;
        $form->handleRequest($request);
        $clickUrl = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            if (in_array($formData['method'], ['autologin_with_conf_no', 'retrieve_by_conf_no'])) {
                $className = "\\TAccountChecker" . ucfirst(strtolower($formData['providerCode']));
                if (class_exists($className)) {
                    /** @var \TAccountChecker $parser */
                    $parser = new $className;
                    $allowedConfFields = array_keys($parser->GetConfirmationFields());
                    $confFields = array_keys($this->convertAnswers($formData['confNoFields']));
                    sort($allowedConfFields);
                    sort($confFields);
                    if ($confFields !== $allowedConfFields) {
                        $form->addError(new FormError("Invalid confirmation fields " . json_encode($confFields) . ", allowed: " . implode(", ", $allowedConfFields)));
                    }
                }
            }

            if ($formData['serverCheck'] && $formData['method'] !== 'parse') {
                $form->addError(new FormError("Server check is only available for parse method"));
            }

            if ($formData['serverCheck']) {
                $state = [];
                $serverCheckConfig = $serverConfigFactory->getConfig($formData['providerCode'], $state);
                if ($serverCheckConfig === null) {
                    $form->addError(new FormError("Missing {$formData['providerCode']}ExtensionServerConfig class"));
                }
            }

            if ($formData['useClickUrl'] && !in_array($formData['method'], ['autologin_with_conf_no', 'autologin'])) {
                $form->addError(new FormError("use Click URL is only available on autologin and autologin_with_conf_no methods"));
            }

            if ($formData['clickUrl'] && !in_array($formData['method'], ['autologin_with_conf_no', 'autologin'])) {
                $form->addError(new FormError("Click URL is only available on autologin and autologin_with_conf_no methods"));
            }

            if ($formData['clickUrl'] && in_array($formData['method'], ['autologin_with_conf_no', 'autologin']) && $formData['clickUrl']) {
                $form->addError(new FormError("You can't set both 'Click URL' and 'use Click URL', select only one option"));
            }

            if ($formData['useClickUrl'] && in_array($formData['method'], ['autologin_with_conf_no', 'autologin'])) {
                $providerClickUrl = $connection->fetchOne("select ClickURL from Provider where Code = ?", [$formData['providerCode']]);
                if (empty($providerClickUrl)) {
                    $form->addError(new FormError("Click URL is empty for provider " . $formData['providerCode']));
                } else {
                    $clickUrl = $providerClickUrl;
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $credentials = new Credentials(
                $formData['login'] ?? '',
                $formData['login2'],
                $formData['login3'],
                $formData['password'] ?? '',
                $this->convertAnswers($formData['answers'])
            );

            $loginId = '';
            if ($formData['loginId']) {
                $loginId = $formData['loginId'] . ':' . $credentials->getLogin();
            }

            if (!$formData['serverCheck']) {
                $session = $sessionManager->create();
                $requestId = $session->getSessionId();
            } else {
                $requestId = self::NO_SESSION_REQUEST_PREFIX . bin2hex(random_bytes(8));
            }

            $debugConnectionToken = $centrifuge->generateConnectionToken($requestId . '_debug', time() + 15*60);

            $memcached->set(
                $this->getMemcachedKey($requestId),
                [
                    'credentials' => $credentials,
                    'providerCode' => $formData['providerCode'],
                    'method' => $formData['method'],
                    'loginId' => $loginId,
                    'parseItineraries' => $formData['parseItineraries'],
                    'parsePastItineraries' => $formData['parsePastItineraries'],
                    'parseHistory' => $formData['parseHistory'],
                    'historyStartDate' => $formData['historyStartDate'],
                    'loglevel' => $formData['loglevel'],
                    'confNoFields' => $this->convertAnswers($formData['confNoFields']),
                    'keepTabOpen' => $formData['keepTabOpen'],
                    'serverCheck' => $formData['serverCheck'],
                    'clickUrl' => $clickUrl ?? $formData['clickUrl'],
                    'background' => $formData['background'],
                    'mailboxConnected' => $formData['mailboxConnected'],
                ],
                60 * 30
            );

        }

        if ($request->headers->get('Accept') === 'application/json' && $form->isSubmitted() && !$form->isValid()) {
            return new JsonResponse([
                'errors' => array_map(fn(FormError $error) => ["message" => $error->getMessage(), "field" => $error->getOrigin()->getName()], iterator_to_array($form->getErrors(true, true))),
            ]);
        }

        if ($request->headers->get('Accept') === 'application/json' && $form->isSubmitted() && $form->isValid()) {
            return new JsonResponse([
                'session' => $session,
                'debugConnectionToken' => $debugConnectionToken,
            ]);
        }

        return $this->render('@AwardWalletMain/debugExtension.html.twig', [
            'form' => $form->createView(),
            'title' => 'Debug extension v3',
            'requestId' => $requestId,
            'session' => $session ?? null,
            'serverCheck' => $formData['serverCheck'] ?? false,
            'debugConnectionToken' => $debugConnectionToken ?? null,
        ]);
    }

    /**
     * @Route("/admin/run-extension/{requestId}", name="run_extension", methods={"GET"})
     */
    public function runExtensionAction(
        string                  $requestId,
        ParserRunner            $parserRunner,
        Logger                  $logger,
        \Memcached              $memcached,
        Client                  $centrifuge,
        ParserFactory           $parserFactory,
        ClientFactory           $clientFactory,
        ProviderInfoFactory     $providerInfoFactory,
        DummyNotificationSender $notificationSender,
        Request                 $request,
        SeleniumSessionManager  $seleniumSessionManager,
        ServerCheckOptionsFactory $serverCheckOptionsFactory
    )
    {
        $data = $memcached->get($this->getMemcachedKey($requestId));
        if ($data === null) {
            throw $this->createNotFoundException('Session not found, refresh the page');
        }

        $logger->pushHandler(new CentrifugeLogHandler($centrifuge, '#' . $requestId . '_debug', $data['loglevel']));
        $parserLogger = new ParserLogger($logger);
        $providerInfo = $providerInfoFactory->createProviderInfo($data['providerCode']);
        $errorFormatter = new ErrorFormatter($providerInfo->getDisplayName(), $providerInfo->getShortName());
        try {
            /** @var Credentials $credentials */
            $credentials = $data['credentials'];
            /** @var LoginWithIdInterface $parser */
            $parser = $parserFactory->getParser(
                $data['providerCode'],
                $parserLogger,
                new SelectParserRequest($credentials->getLogin2(), $credentials->getLogin3()),
                new ParserContext($providerInfo, false, $data['serverCheck'], $data['background'], $data['mailboxConnected']),
                $notificationSender,
                new CaptchaServices($logger, new Context('awardwallet', $data['providerCode'], 1, $request->headers->get('User-Agent')), $notificationSender, $this->captchaProviders),
                new State(),
                new DebugWatchdogControl($logger)
            );

            if ($data['serverCheck']) {
                $accountOptions = new AccountOptions($credentials->getLogin(), $credentials->getLogin2(), $credentials->getLogin3(), false);
                $state = [];
                $serverCheckOptions = $serverCheckOptionsFactory->getServerCheckOptions($data['providerCode'], $accountOptions, $state);
                $seleniumSession = $seleniumSessionManager->start($serverCheckOptions, [], null);
                $sessionId = $seleniumSession->extensionSessionId;
            } else {
                $sessionId = $requestId;
            }

            $client = $clientFactory->createClient($sessionId, $parserLogger->getFileLogger(), $errorFormatter, false);

            if ($data['method'] === 'autologin') {
                $result = $parserRunner->loginWithLoginId($parser, $client, new LoginWithLoginIdRequest($credentials, true, $data['loginId'] ?? '', $parserLogger->getFileLogger(), $data['clickUrl'] ?? null));
                $result->loginResult->error = $errorFormatter->format($result->loginResult->error);
            } elseif ($data['method'] === 'autologin_with_conf_no') {
                /** @var LoginWithConfNoInterface $parser */
                $result = $parserRunner->loginWithConfNo($parser, $client, $data['confNoFields'], new ConfNoOptions(false), $data['clickUrl'] ?? null);
                $tab = $result->tab;
            } elseif ($data['method'] === 'retrieve_by_conf_no') {
                /** @var LoginWithConfNoInterface $parser */
                $result = $parserRunner->loginWithConfNo($parser, $client, $data['confNoFields'], new ConfNoOptions(false), null);
                /** @var RetrieveByConfNoInterface $parser */
                $tab = $result->tab;
                $master = new Master('main');
                $master->addPsrLogger($logger);
                $parserLogger = new ParserLogger($logger);
                $parserRunner->retrieveByConfNo($parser, $result->tab, $master, $data['confNoFields'], new ConfNoOptions(false), $parserLogger->getFileLogger());
                $result = $master->toArray();
            } elseif ($data['method'] === 'parse') {
                $result = $this->parse($parserRunner, $parser, $client, $data, $logger, $errorFormatter, $data['keepTabOpen']);
            } else {
                throw new \Exception("Unknown method: {$data['method']}");
            }
        } finally {
            $logger->popHandler();
            // commented out to show logs
//            $parserLogger->cleanup();
        }

        return $this->render('@AwardWalletMain/runExtension.html.twig', [
            'title' => 'Extension run result',
            'result' => ParserRunner::formatJsonData($result),
            'logDir' => $parserLogger->getLogDir(),
        ]);
    }

    /**
     * @Route("/extension-response", name="extension_response_options", methods={"OPTIONS"})
     */
    public function extensionResponseOptionsAction(Request $request) : Response
    {
        return new Response("ok", 200, ['Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Methods' => 'POST, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type']);
    }

    /**
     * @Route("/extension-response", name="puppeteer_extension_response", methods={"POST"})
     */
    public function extensionResponseAction(Request $request, LoggerInterface $logger, ResponseReceiver $responseReceiver) : Response
    {
        $data = json_decode($request->getContent(), true);
        $responseReceiver->receive(new ExtensionResponse($data['sessionId'], $data['result'] ?? null, $data['requestId']));

        return new JsonResponse("ok", 200, ['Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Methods' => 'POST, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type']);
    }

    /**
     * @Route("/extension-save-login-id", name="extension_save_login_id_options", methods={"OPTIONS"})
     */
    public function seleniumExtensionSaveLoginIdOptionsAction(Request $request) : Response
    {
        return new Response("ok", 200, ['Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Methods' => 'POST, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type']);
    }

    /**
     * @Route("/extension-save-login-id", name="extension_save_login_id", methods={"POST"})
     */
    public function extensionSaveLoginIdAction(Request $request, LoggerInterface $logger) : Response
    {
        $data = json_decode($request->getContent(), true);
        $logger->info("received login id", $data);

        return new JsonResponse("ok", 200, ['Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Methods' => 'POST, OPTIONS', 'Access-Control-Allow-Headers' => 'Content-Type']);
    }

    private function getMemcachedKey(string $extensionSessionId) : string
    {
        return "ext_sess_" . $extensionSessionId;
    }

    private function convertAnswers(?string $answers) : array
    {
        $result = [];
        $lines = explode("\n", $answers);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === "") {
                continue;
            }

            $pair = explode("=", $line);
            if (count($pair) != 2) {
                throw new \Exception("Answers/ConfNo fields expected in format Question=Answer, one per line");
            }

            $result[$pair[0]] = $pair[1];
        }

        return $result;
    }

    public function parse(ParserRunner $parserRunner, LoginWithIdInterface $parser, \AwardWallet\ExtensionWorker\Client $client, $data, Logger $logger, ErrorFormatter $errorFormatter, bool $keepTabOpen)
    {
        $parserLogger = new ParserLogger($logger);
        $result = $parserRunner->loginWithLoginId($parser, $client, new LoginWithLoginIdRequest($data['credentials'], null, $data['loginId'] ?? '', $parserLogger->getFileLogger(), null));
        $result->loginResult->error = $errorFormatter->format($result->loginResult->error);

        if (!$result->loginResult->success && !$keepTabOpen) {
            $result->tab->close();
        }

        if ($result->loginResult->success) {
            $master = new Master('main');
            $master->addPsrLogger($logger);
            /** @var ParseInterface $parser */
            try {
                $parserRunner->parseAll(
                    $parser,
                    $result->tab,
                    $master,
                    new ParseAllOptions(
                        $data['credentials'],
                        $data['parseItineraries'] ? new ParseItinerariesOptions($data['parsePastItineraries']) : null,
                        $data['parseHistory'] ? new ParseHistoryOptions($data['historyStartDate'], [], false) : null,
                    ),
                    $parserLogger->getFileLogger()
                );
            }
            catch (\CheckException $exception) {
                return new LoginWithIdResult(new LoginResult(false, $errorFormatter->format($exception->getMessage()), null, $exception->getCode()), "", $result->tab);
            } finally {
                if (!$keepTabOpen) {
                    $result->tab->close();
                }
            }

            $result = $master->toArray();
        }

        return $result;
    }

}
