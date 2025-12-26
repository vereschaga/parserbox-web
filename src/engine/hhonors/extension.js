var plugin = {
    // hideOnStart: (applicationPlatform == 'android') ? false : true,//todo
    // options: {
    //     logHtml: false
    // },
    hideOnStart: true,//todo

    // mobileUserAgent: typeof (applicationPlatform) != "undefined" && applicationPlatform === "android" ? 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Mobile Safari/537.36' : "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.2 Safari/605.1.15",
    mobileUserAgent: typeof (applicationPlatform) != "undefined" && applicationPlatform === "android" ? 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Mobile Safari/537.36' : null,

    clearCache: (typeof(applicationPlatform) != 'undefined' && applicationPlatform == 'android') ? true : false,
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),
    // keepTabOpen: true,

    hosts: {
        'hhonors1.hilton.com': true,
        'secure.hilton.com': true,
        'www.hilton.com': true,
        'www3.hilton.com': true,
        'secure3.hilton.com': true,
        'hhonors3.hilton.com': true,
        'hiltonhonors3.hilton.com': true,
        '.hilton.com': true,
        'www.hiltonhotels.de': true,
        'www.hiltonhotels.it': true
    },

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.hilton.com/en/hilton-honors/guest/my-account/';
    },

    getFocusTab: function (account, params) {
        return true;
    },

    // for Firefox, refs #19191, #note-24
    getXMLHttp: function () {
        if (typeof content !== 'undefined' && content && content.XMLHttpRequest) {
            return new content.XMLHttpRequest();
        }
        return new XMLHttpRequest();
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log('Current URL: ' + document.location.href);
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account)) {
                        plugin.loginComplete(params);
                    } else {
                        plugin.logout(params);
                    }
                } else {
                    plugin.login(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                if (document.location.href === 'https://www.hilton.com/en/hilton-honors/login/?forwardPageURI=%2Fen%2Fhilton-honors%2Fguest%2Fmy-account%2F') {
                    // retries
                    browserAPI.log(">>> retry");
                    let retry = $.cookie("hilton.com_aw_retry_" + params.account.login);
                    provider.logBody("startPage-" + retry);
                    if ((retry === null || typeof(retry) === 'undefined') || retry < 3) {
                        if (retry === null || typeof(retry) === 'undefined')
                            retry = 0;
                        provider.logBody("lastPage-" + retry);
                        browserAPI.log(">>> Retry: " + retry);
                        retry++;
                        $.cookie("hilton.com_aw_retry_" + params.account.login, retry, { expires: 0.01, path:'/', domain: '.hilton.com', secure: true });
                        provider.setNextStep('loadLoginForm', function () {
                            document.location.href = plugin.getStartingUrl(params);
                        });
                        return;
                    }// if (retry == null || retry < 3)
                }// if (document.location.href === 'https://www.hilton.com/en/hilton-honors/login/?forwardPageURI=%2Fen%2Fhilton-honors%2Fguest%2Fmy-account%2F')

                clearInterval(start);
                provider.logBody("lastPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (counter > 20)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        browserAPI.log('Current URL: ' + document.location.href);
        if ($('button:contains("Sign Out")').length > 0) {
            provider.logBody("loggedInPage");
            browserAPI.log("logged in");
            return true;
        }
        let frame = $('iframe#hiltonLoginFrame, iframe[data-e2e="loginIframe"]');
        browserAPI.log("frame -> " + frame.length);
        if (frame) {
            browserAPI.log("frame form -> " + frame.contents().find('input[name = "username"]').closest('form').length);
        }
        if (
            frame.length > 0
            && frame.contents().find('input[name = "username"]').closest('form').length > 0
        ) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('form:has(input[name = "username"])').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('button[aria-controls = "userMenu"]').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        let logout = $('a[href *= "hilton-honors/guest/profile"]:visible, a[href *= "logout"]:visible, button:contains("Sign Out"):visible, button:contains("Sign out"):visible');
        if (logout.length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        // provider error
        let errors = plugin.checkErrors();
        if (errors.length > 0) {
            provider.setError([errors.text(), util.errorCodes.providerError], true);
            return null;
        }
        // No server is available to handle this request.
        if (
            frame.length > 0
            && frame.contents().find('body:contains("No server is available to handle this request.")').length > 0
        ) {
            provider.setError([util.providerErrorMessage, util.errorCodes.providerError], true);
            return null;
        }

        return null;
    },

    checkErrors: function() {
        browserAPI.log('>>> checkErrors');
        let errors = $('h1:contains("Customer profiles are unavailable")');
        if (errors.length === 0) {
            errors = $('p:contains("Unfortunately, we are having technical difficulties and are unable to complete your request"):visible');
        }

        return errors;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        browserAPI.log('Current URL: ' + document.location.href);
        var name = util.findRegExp($('#welcome').text(), /Welcome\s([^<]+)/i);
        browserAPI.log("name: " + name);
        if (!name) {
            name = util.findRegExp($('section>section>section>section:contains("Hi,"):eq(0)').text(), /Hi,\s([^<]+)/i);
            browserAPI.log("name: " + name);
        }
        if (!name) {
            name = util.findRegExp($('header button:contains("Hi,"):eq(0)').text(), /Hi,\s([^<]+)/i);
            browserAPI.log("name: " + name);
        }
        var number = util.findRegExp($('span:contains("Hilton Honors number") + span:eq(0)').parent().first().contents().eq(2).text(), /#\s*(\d+)\s*/i);
        if (!number || number === '')
            number = util.findRegExp($('strong:contains("Member number:") + span:eq(0)').text(), /\s*(\d+)\s*/i);
        browserAPI.log("number: " + number);
        if (name)
            name = name.toLowerCase();
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name && number
            && (account.properties.Name.toLowerCase().indexOf(name) !== -1 || name.indexOf(account.properties.Name.toLowerCase()) !== -1)
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (util.filter(number) === account.properties.Number) );
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        browserAPI.log('Current URL: ' + document.location.href);
        provider.setNextStep('loadLoginForm2', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loadLoginForm2: function (params) {
        browserAPI.log("loadLoginForm2");
        browserAPI.log('Current URL: ' + document.location.href);

        if (document.location.href === 'https://secure3.hilton.com/en/hh/customer/login/index.htm') {
            return provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }

        plugin.start(params);
    },

    fetch(...args) {
        browserAPI.log('fetch: ' + args[0]);
        if (typeof content !== 'undefined' && content && content.fetch) {
            return content.fetch(...args);
        }
        return fetch(...args);
    },

    logout: async function (params) {
		browserAPI.log("logout");

        try {
            var wso2AuthToken = JSON.parse($.cookie('wso2AuthToken'));
        } catch (e) {
            browserAPI.log('get brand guest failed: token or guest id not found');
            return [];
        }
        if (
            wso2AuthToken === null
            || typeof wso2AuthToken !== 'object'
            || typeof wso2AuthToken.accessToken === undefined
            || typeof wso2AuthToken.guestId === undefined
        ) {
            browserAPI.log('get brand guest failed: token or guest id not found');
            return [];
        }


        await plugin.fetch('https://www.hilton.com/dx-customer/auth/guests/logout?appName=dx_guests_app', {
            method: 'POST',
            headers: {
                'Accept': 'application/json; charset=utf-8',
                'Authorization': 'Bearer ' + wso2AuthToken.accessToken,
                'x-requested-with' : '',
                'Cache-Control': 'max-age=0',
                'Content-Type': 'application/json; charset=utf-8'
            },
        });
        plugin.loadLoginFormLogout();

       /* $('header button:contains("Hi,"):eq(0)').click();
        util.sendEvent($('header button:contains("Hi,"):eq(0)').get(0), 'mousedown');
        plugin.sendEvent('header button:contains("Hi,")', 'click');
        $('header button:contains("Hi,"):eq(0)').click();*/

        /*
        setTimeout(function () {
            const logout = $('#sign-out');
            provider.setNextStep('loadLoginForm', function () {
                if (logout.length > 0) {
                    browserAPI.log("button click");
                    logout.get(0).click();

                    setTimeout(function () {
                        plugin.start(params);
                    }, 4000)
                }
            });
        }, 1000);
        */
    },

    loadLoginFormLogout: function (params) {
        browserAPI.log("loadLoginForm");
        browserAPI.log('Current URL: ' + document.location.href);
        provider.setNextStep('loadLoginForm', function () {
            document.location.reload();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = "http://hhonors3.hilton.com/en/index.html";
            });
            return;
        }

        if (plugin.isLoggedIn()) {
            browserAPI.log(">>> Should be logout here");
        }

        setTimeout(function () {
        // let frame = $('iframe#hiltonLoginFrame, iframe[src="https://www.hilton.com/en/auth2/guest/login/"]').contents();
        browserAPI.log(">>> success code update");
        // let form = frame.find('input#username').closest('form');
        let form = $('form:has(input[name = "username"])');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            let usernameInput = form.find('input#username');
            let passwordInput = form.find('input#password');
            // usernameInput.val(params.account.login);
            // passwordInput.val(params.account.password);

            let reactCode = `
                function triggerInput(selector, enteredValue) {
                    // let iframe = document.querySelector('iframe[title="Hilton Honors Login Form"], iframe[title="frameTitle"]');
                    //
                    // if (!iframe && document.getElementById('hiltonLoginFrame')) {
                    //     iframe = document.getElementById('hiltonLoginFrame');
                    // }
                
                    // let input = iframe.contentWindow.document.querySelector(selector);
                    let input = document.querySelector(selector);
                    input.dispatchEvent(new Event('focus'));
                    input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                    let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                    nativeInputValueSetter.call(input, enteredValue);
                    let inputEvent = new Event("input", { bubbles: true });
                    input.dispatchEvent(inputEvent);
                };
                triggerInput('input[name = "username"]', "`+ params.account.login +`");
                triggerInput('input[name = "password"]', "`+ params.account.password.replace(/\\/g,'\\\\') +`");
            `;

            provider.eval(reactCode);

            // let captcha = frame.find('iframe[src *= "/_sec/cp_challenge/recaptcha"]:visible, iframe[src *= "recaptcha/api2/anchor"]:visible');
            let captcha = form.find('iframe[src *= "/_sec/cp_challenge/recaptcha"]:visible, iframe[src *= "recaptcha/api2/anchor"]:visible');
            if (captcha.length > 0) {
                captchaWorkaround();
            } else {
                browserAPI.log(">>> Captcha not found");
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[data-e2e="signInButton"]').get(0).click();

                     setTimeout(function () {
                         // let captcha = frame.find('iframe[src *= "/_sec/cp_challenge/recaptcha"]:visible, iframe[src *= "recaptcha/api2/anchor"]:visible');
                         let captcha = form.find('iframe[src *= "/_sec/cp_challenge/recaptcha"]:visible, iframe[src *= "recaptcha/api2/anchor"]:visible');
                         if (captcha.length > 0) {
                             captchaWorkaround();
                         } else {
                             browserAPI.log(">>> Captcha not found 2");
                             plugin.checkLoginErrors(params);
                         }
                     }, 5000);
                });
            }
            return;
        }

        if (
            $('p:contains("net::ERR_CONNECTION_CLOSED"):visible').length > 0
            && $('h2:contains("Página web no disponible\n"):visible').length > 0
        ) {
            provider.setError(util.errorMessages.providerErrorMessage, true);
            return;
        }
        const message = $('h1:contains("Something went wrong"):visible');
        if (message.length > 0) {
            provider.setError([message, util.errorCodes.providerError], true);
            return;
        }
        if ($('h1:contains("502 Bad Gateway"):visible').length > 0) {
            provider.setError(util.errorMessages.providerErrorMessage, true);
            return;
        }

        function captchaWorkaround() {
            browserAPI.log(">>> captchaWorkaround");
            if (provider.isMobile) {
                provider.command('show', function () { });
            }
            provider.reCaptchaMessage();
            let chat = $('#tfs_invite_mvp_question_button_aiva_ohw_container');
            if (chat.length > 0) {
                browserAPI.log("hide chat iframe");
                chat.hide();
            }
            let popup = $('#consent_blackbar');
            if (popup.length > 0) {
                browserAPI.log("hide policy popup");
                popup.hide();
            }

            browserAPI.log("waiting...");
            provider.setNextStep('checkLoginErrors', function () {
                let counter = 0;
                let login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    /*
                    if (usernameInput.val().length === 0) {
                        browserAPI.log("submitting saved credentials");
                        /*
                         usernameInput = form.find('input#username');
                         passwordInput = form.find('input#password');
                         usernameInput.val(params.account.login);
                         passwordInput.val(params.account.password);
                         * /
                        provider.eval(reactCode);
                    }
                    */
                    let balance = $('dt:contains("Points:") + dd:eq(0):visible, strong:contains("Points:") + span:eq(0):visible, div[data-testid="honorsPointsBlock"] > span:eq(0):visible');
                    let balance2 = $('span:contains("Points"):visible').prev('span:eq(0)');
                    if (
                        counter > 120
                        || (balance.length > 0 && balance.text() !== '')
                        || (balance2.length > 0 && balance2.text() !== '')
                        || $('iframe#hiltonLoginFrame, iframe[src="https://www.hilton.com/en/auth2/guest/login/"]').contents().find('div#errorContent:visible, span[data-e2e="errorText"]:visible').length > 0
                        || $('span[data-e2e="errorText"]:visible').length > 0
                    ) {
                        clearInterval(login);
                        plugin.checkLoginErrors(params);
                    }
                    counter++;
                }, 1000);
            });
        }

        provider.logBody('loginPage');
        provider.setError(util.errorMessages.loginFormNotFound, true);
        }, 5000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        browserAPI.log('Current URL: ' + document.location.href);

        // let frame = $('iframe#hiltonLoginFrame, iframe[data-e2e="loginIframe"]').contents();
        let frame = $('form:has(input[name = "username"])');
        if (frame.length > 0) {
            provider.logBody('checkLoginErrorsPage');
            let error = frame.find('span[data-e2e="errorText"]:visible').text().trim();
            if (error.length > 0) {
                if (
                    /Your login didn’t match our records\./.test(error)
                    || /Please try again\. Be careful: too many attempts will lock your account\./.test(error)
                ) {
                    provider.setError([error, util.errorCodes.invalidPassword], true);
                    return;
                }

                if (/Something went wrong, and your request wasn't submitted\./.test(error)) {
                    provider.setError([error, util.errorCodes.providerError], true);
                    return;
                }

                if (/We need your username and password to login\./.test(error)) {
                    provider.setError([error, util.errorCodes.invalidPassword], true);
                    return;
                }
                if (/Please confirm you're not a robot to continue\./.test(error)) {
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                    return;
                }

                browserAPI.log('>> Error: ' + error);
                provider.complete();
                // provider.setError(error, true);
                return;
            }
            let captcha = frame.find('iframe[src *= "/_sec/cp_challenge/recaptcha"]:visible, iframe[src *= "recaptcha/api2/anchor"]:visible');
            if (captcha.length > 0) {
                provider.setError(util.errorMessages.captchaErrorMessage, true);
                return;
            }
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.logBody("loginCompletePage");
		if (typeof(params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItinerariesUS', function(){
                document.location.href = 'https://www.hilton.com/en/hilton-honors/guest/activity/';
            });
		}
        else {
            // if bug in account   // refs #5283
            if (document.location.href === 'https://secure3.hilton.com/en_US/hh/error/unknown.htm'
                // refs #14869
                || $('p:contains("Our Apologies! We\'re currently working out our issues so we can be better for you."):visible').length > 0
                || (!util.findRegExp(document.location.href, /(^https:\/\/secure3\.hilton\.com\/en\/hh\/customer\/account\/index\.htm)/)
                    && document.location.href !== plugin.getStartingUrl(params)
                    && !util.findRegExp(document.location.href, /(^https:\/\/www\.hilton\.com\/en\/hilton-honors\/guest\/my-account\/)/)
                    && !util.stristr(document.location.href, 'https://www.hilton.com/en/hilton-honors/login/')
                )
            ) {
                    browserAPI.log("loading main page");
                    provider.setNextStep('loadAccount', function () {
                        document.location.href = 'http://www3.hilton.com/en/index.html';
                    });
            }
            else
                plugin.loadAccount(params);
        }
	},

	toItinerariesUS:function (params) {
        browserAPI.log("toItinerariesUS");
		var link = $('a[href*="/book/reservation/manage/"][href*="confirmationNumber=' + params.account.properties.confirmationNumber + '"]');
		if (link.length > 0) {
			var href = link.attr('href');
            if (href.indexOf('hilton.com') === -1)
                href = 'https://www.hilton.com' + href;
			provider.setNextStep('itLoginComplete', function(){
                document.location.href = href;
            });
		}
		else {
			if (typeof(params.account.properties.confFields) != "object") {
                provider.setError(util.errorMessages.itineraryNotFound, true);
            }
			else {
				provider.setNextStep('getConfNoItinerary', function(){
                    document.location.href = "https://secure3.hilton.com/en_US/hh/reservation/find/index.htm";
                });
			}
		}
	},

	getConfNoItinerary:function (params) {
        browserAPI.log("getConfNoItinerary");
		var properties = params.account.properties.confFields;
		var form = $('form#findReservationForm');
		if (form.length > 0) {
            browserAPI.log('Find Itinerary, v.1');
			form.find('input[name="confirmationNumber"]').val(properties.ConfNo);
			form.find('input[name="lastNameOrCCLastFourDigits"]').val(properties.LastName);
			provider.setNextStep('itLoginComplete', function(){
                form.submit();
            });
		}
		else {
		    form = $('form#formFindReservation');
            if (form.length > 0) {
                browserAPI.log('Find Itinerary, v.2');
                form.find('input[name="reservation_findReservation_1{actionForm.confirmationNumber}"]').val(properties.ConfNo);
                form.find('input[name="reservation_findReservation_1{actionForm.lastName}"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function(){
                    form.submit();
                });
            }
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }
	},

	itLoginComplete:function (params) {
        browserAPI.log("itLoginComplete");
		provider.complete();
	},

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        browserAPI.log('Current URL: ' + document.location.href);
        if (params.autologin) {
            browserAPI.log(">>> Only autologin");
            provider.complete();
            return;
        }

        browserAPI.log('Current URL: ' + document.location.href);
        if (
            document.location.href !== plugin.getStartingUrl(params)
            && !util.stristr(document.location.href, 'https://www.hilton.com/en/hilton-honors/login/')
        ) {
            browserAPI.log('>> Opening Account page...');
            return provider.setNextStep('parse', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }

        let counter = 0;
        let loadAccount = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let balance = $('dt:contains("Points:") + dd:eq(0):visible, strong:contains("Points:") + span:eq(0):visible, div[data-testid="honorsPointsBlock"] > span:eq(0):visible');
            let balance2 = $('span:contains("Points"):visible').prev('span:eq(0)');
            if (
                counter > 30
                || (balance.length > 0 && balance.text() !== '')
                || (balance2.length > 0 && balance2.text() !== '')
            ) {
                browserAPI.log(">>> balance: '" + balance.text() + "'");
                browserAPI.log(">>> balance 2: '" + balance2.text() + "'");
                clearInterval(loadAccount);
                plugin.parse(params);
            }
            counter++;
        }, 500);
    },

    parse: function (params) {
        browserAPI.log("parse");
        browserAPI.log('Current URL: ' + document.location.href);

        if (util.stristr(document.location.href, 'https://www.hilton.com/en/hilton-honors/login')) {
            browserAPI.log('>> failed auth');

            // retries
            browserAPI.log(">>> retry");
            let retry = $.cookie("hilton.com_aw_retry_" + params.account.login);
            provider.logBody("parsePage-" + retry);
            if ((retry === null || typeof(retry) === 'undefined') || retry < 2) {
                if (retry === null || typeof(retry) === 'undefined')
                    retry = 0;
                provider.logBody("parsePage-" + retry);
                browserAPI.log(">>> Retry: " + retry);
                retry++;
                $.cookie("hilton.com_aw_retry_" + params.account.login, retry, { expires: 0.01, path:'/', domain: '.hilton.com', secure: true });
                provider.setNextStep('login', function () {
                    document.location.href = plugin.getStartingUrl(params);
                });
                return;
            }// if (retry == null || retry < 3)

            provider.setError(["Your login attempt has been blocked by this website.", util.errorCodes.providerError], true);/*review*/

            return;
        }

        if (!provider.isMobile) {
            provider.updateAccountMessage();
        }
        let data = {};
        // HHonors Number
        var number = util.findRegExp($('span:contains("Hilton Honors number") + span:eq(0)').parent().first().contents().eq(2).text(), /#\s*(\d+)\s*/i);
        if (!number || number === '') {
            number = util.findRegExp($('strong:contains("Member number:") + span:eq(0)').text(), /\s*(\d+)\s*/i);
        }
        if (number && number.length > 0) {
            data.Number = util.filter(number);
            browserAPI.log("HHonors Number: " + data.Number );
        } else if (provider.isMobile) {
            number = util.findRegExp($('div.userSummary-row:eq(1) > span:contains("#")').text(), /\#\s*([\d]+)/i);
            data.Number = number;
            browserAPI.log("HHonors Number (mobile version): " + data.Number );
        } else {
            browserAPI.log("HHonors Number not found");
        }
        // Name
        var name = util.findRegExp($('#welcome').text(), /Welcome\s([^<]+)/i);
        browserAPI.log("name: " + name);
        if (!name) {
            browserAPI.log("Name not found");
            name = util.findRegExp($('section > section > section > section:contains("Hi,"):eq(0)').text(), /Hi,\s([^<]+)/i);
            browserAPI.log("name: " + name);
        }
        if (!name) {
            browserAPI.log("Name not found");
            name = util.findRegExp($('header button:contains("Hi,"):eq(0)').text(), /Hi,\s([^<]+)/i);
            browserAPI.log("name: " + name);
        }
        /*
        if (!name && guestInfo) {
            name = guestInfo.personalinfo.name.firstName || null;
            browserAPI.log("name: " + name);
        }
        */
        if (name) {
            name = util.beautifulName(name);
            browserAPI.log("Set Name: " + name );
            data.Name = name;
        }
        // Status
        data.Status = null;
        var status = $('span:contains("status!") > strong, section:has(span:contains("Current tier")) > span:eq(0)');
        var lifetime = null;
        if (status.length === 0) {
            status = $('span:contains("You need"), span:contains("No expiration & no requalification required!")').prev('span:eq(0):visible');
            lifetime = status.prev('span:eq(0):visible');
        }
        if (status.length === 0) {
            browserAPI.log("Status not found, try v2");
            status = $('span:contains("current tier")').prev('span');
        }
        if (status.length === 0) {
            browserAPI.log("Status not found, try v3");
            status = $('div[data-testid="honorsTierInfoBlock"] span');
        }
        if (status.length > 0) {
            data.Status = util.beautifulName(util.filter(status.text()));
            if (lifetime && lifetime.length > 0 && util.filter(lifetime.text()) == 'Lifetime') {
                data.Status = 'Lifetime ' + data.Status;
            }
            browserAPI.log("Status: " + data.Status);
        }
        /*
        if (data.Status === null && guestInfo) {
            data.Status = guestInfo.hhonors.summary.tierName || null;
        }
        */

        let brandGuest = plugin.getBrandGuest(params);
        // Qualification Period
        var yearBegins = new Date('1 JAN' + new Date().getUTCFullYear() + ' UTC') / 1000;
        browserAPI.log("Year Begins: " + util.trim(yearBegins) );
        data.YearBegins = yearBegins;
        // Stays
        var stays = $('button[data-view = "stays"], ul[role = "tablist"] > li:contains("Stay")');
        if (stays.length > 0) {
            data.Stays = util.findRegExp( stays.text(), /([\d]+)/i);
            browserAPI.log("Stays: " + data.Stays );
        }
        else {
            browserAPI.log("Stays not found");
            /*
            if (nextData.length > 0) {
                data.Stays = util.findRegExp(nextData.text(), /"qualifiedStays":\s*([^,]+)/);
                browserAPI.log("Stays: " + data.Stays );
            }
            */
        }
        // Nights
        data.Nights = plugin.objectVal(brandGuest, ['data', 'guest', 'hhonors', 'summary', 'qualifiedNights']);
        browserAPI.log("Nights: " + data.Nights);
        // BasePoints
        data.BasePoints = plugin.objectVal(brandGuest, ['data', 'guest', 'hhonors', 'summary', 'qualifiedPointsFmt']);
        browserAPI.log("BasePoints: " + data.BasePoints);
        // To Maintain Current Level
        data.ToMaintainCurrentLevel = plugin.objectVal(brandGuest, ['data', 'guest', 'hhonors', 'summary', 'qualifiedNightsMaint']);
        browserAPI.log("ToMaintainCurrentLevel: " + data.ToMaintainCurrentLevel);
        // To Reach Next Level
        data.ToReachNextLevel = plugin.objectVal(brandGuest, ['data', 'guest', 'hhonors', 'summary', 'qualifiedNightsNext']);
        browserAPI.log("ToReachNextLevel: " + data.ToReachNextLevel);
        // Points To Next Level
        data.PointsToNextLevel = plugin.objectVal(brandGuest, ['data', 'guest', 'hhonors', 'summary', 'qualifiedPointsNextFmt']);
        browserAPI.log("PointsToNextLevel: " + data.PointsToNextLevel);

        // Balance - Current Points
        data.Balance = null;
        var balance = $('dt:contains("Points:") + dd:eq(0), strong:contains("Points:") + span:eq(0)');
        if (balance.length === 0) {
            balance = $('span:contains("Points")').prev('span:eq(0)');
        }
        if (balance.length === 0) {
            balance = $('div[data-testid="honorsPointsBlock"] > span:eq(0)');
            browserAPI.log("Balance (mobile version) length: " + balance.length );
        }
        if (balance.length > 0) {
            balance = util.findRegExp( balance.text(), /([\d\.\,]+)/i);
            browserAPI.log("Balance: " + balance );
            data.Balance = balance;

            if (balance === null) {
                browserAPI.log("remove Balance");
                // var updMessage = $('p:contains("We\'re updating your Hilton Honors tier tracker. Please check back later."):visible');
                // if (updMessage.length > 0) {
                //     provider.setError([updMessage.text(), util.errorCodes.providerError], true);
                //     return;
                // }
                delete data.Balance;
            }
        }

        if ((data.Balance === null || !data.Balance) && brandGuest) {
            data.Balance = plugin.objectVal(brandGuest, ['data', 'guest', 'hhonors', 'summary', 'totalPointsFmt']);

            browserAPI.log("totalPoints === '" + data.Balance + "'");
            // refs #19889
            if (data.Balance === '') {
                browserAPI.log("provider bug fix balance === '" + balance + "'");
                data.Balance = "0";
            }
        }
        if (data.Balance === null) {
            browserAPI.log("Balance is not found");
            provider.logBody('parsePage');
            // refs #14869
            var xCsrfToken = $.cookie("xCsrfToken");
            if ($('p:contains("Our Apologies! We\'re currently working out our issues so we can be better for you."):visible').length > 0 && xCsrfToken) {
                browserAPI.log(">>> Bug in Account");
                $.ajax({
                    url: 'https://secure3.hilton.com/en_US/hi/ajax/nocache/gpmAccount.json?callback=jsonpCallback&xCsrfToken=' + xCsrfToken,
                    async: false,
                    success: function (response) {
                        browserAPI.log('Success');
                        parseProperties(response);
                    },// success: function (response)
                    error: function (response) {
                        browserAPI.log('Error');
                        parseProperties(response);
                    }// error: function (response)
                });// $.ajax({
                function parseProperties(response) {
                    response = response.responseText;
                    browserAPI.log(response);
                    // Tier
                    if (status = util.findRegExp(response, /memberLevel":"([^"]+)/i)) {
                        switch (status) {
                            case 'D':
                                data.Status = "Diamond";
                                break;
                            case 'G':
                                data.Status = "Gold";
                                break;
                            case 'S':
                                data.Status = "Silver";
                                break;
                            case 'B':
                                data.Status = "Blue";
                                break;
                            default:
                                browserAPI.log("Unknown Status was found: " + status );
                                break;
                        }// switch ($tier)
                        browserAPI.log("Status: " + data.Status );
                    }// if (status = util.findRegExp(response, /memberLevel":"([^"]+)/i))
                    else
                        browserAPI.log("Status not found");
                    // HHonors Number
                    var number = util.findRegExp(response, /hhonorsId\":\"([^\"]+)/i);
                    if (number) {
                        data.Number = number;
                        browserAPI.log("HHonors Number: " + data.Number );
                    }
                    else
                        browserAPI.log("HHonors Number not found");
                    // Name
                    var name = util.findRegExp(response, /firstName\":\"([^\"]+)/i) + ' ' + util.findRegExp(response, /lastName\":\"([^\"]+)/i);
                    if (name && name.length > 0) {
                        name = util.beautifulName( util.trim(name) );
                        browserAPI.log("Name: " + name );
                        data.Name = name;
                    }
                    else
                        browserAPI.log("Name not found");
                    // Stays
                    var stays = util.findRegExp(response, /qualifyingStays\":\"([^\"]+)/i);
                    if (stays) {
                        browserAPI.log("Stays: " + stays );
                        data.Stays = stays;
                    }
                    else
                        browserAPI.log("Stays not found");
                    // Nights
                    var nights = util.findRegExp(response, /qualifyingNights\":\"([^\"]+)/i);
                    if (nights) {
                        browserAPI.log("Nights: " + nights );
                        data.Nights = nights;
                    }
                    else
                        browserAPI.log("Nights not found");
                    // Base Points
                    var basePoints = util.findRegExp(response, /basePoints\":\"([^\"]+)/i);
                    if (basePoints) {
                        browserAPI.log("BasePoints: " + basePoints );
                        data.BasePoints = basePoints;
                    }
                    else
                        browserAPI.log("BasePoints not found");
                    // Balance - Current Points
                    var balance = util.findRegExp(response, /points\":\"([^\"]+)/i);
                    if (balance) {
                        browserAPI.log("Balance: " + balance );
                        data.Balance = balance;
                    }
                    else
                        browserAPI.log("Balance is not found");
                    // To Maintain Current Level
                    var toMaintainCurrentLevel = util.findRegExp(response, /pointsToCurrentLevel\":\"([^\"]+)/i);
                    if (toMaintainCurrentLevel) {
                        browserAPI.log("ToMaintainCurrentLevel: " + toMaintainCurrentLevel );
                        data.ToMaintainCurrentLevel = toMaintainCurrentLevel;
                    }
                    else
                        browserAPI.log("ToMaintainCurrentLevel not found");
                    // To Reach Next Level
                    var toReachNextLevel = util.findRegExp(response, /pointsToNextLevel\":\"([^\"]+)/i);
                    if (toReachNextLevel) {
                        browserAPI.log("ToReachNextLevel: " + toReachNextLevel );
                        data.ToReachNextLevel = toReachNextLevel;
                    }
                    else
                        browserAPI.log("ToReachNextLevel not found");
                }// function parseProperties(response)
            }// if ($('p:contains("Our Apologies! We\'re currently working out our issues so we can be better for you."):visible').length > 0 && xCsrfToken)
        }

        browserAPI.log(">>> Free Night Rewards");
        let subAccounts = [];
        const freeNightResponse = plugin.getFreeNightRewards(params);
        browserAPI.log(">>> Free Night Rewards: Ready to use");
        const availableCoupons = freeNightResponse.data.guest.hhonors.amexCoupons.available ?? [];

        for (let coupon in availableCoupons) {
            let code = availableCoupons[coupon].codeMasked.replace('••••• ', '');
            const exp = availableCoupons[coupon].endDate.replace('T00:00:00', '');
            let unixtime = new Date(exp) / 1000;
            let displayName = availableCoupons[coupon].offerName + ' Certificate # ' + availableCoupons[coupon].codeMasked;

            subAccounts.push({
                'Code'           : "hhonorsAmexFreeNightRewards" + code + unixtime,
                'DisplayName'    : displayName,
                'Balance'        : availableCoupons[coupon].points,
                'Number'         : availableCoupons[coupon].codeMasked,
                'ExpirationDate' : unixtime,
            });
        }
        browserAPI.log(JSON.stringify(subAccounts));

        browserAPI.log(">>> Free Night Rewards: Reserved for upcoming stay");
        const reservedCoupons = freeNightResponse.data.guest.hhonors.amexCoupons.held || [];

        for (let coupon in reservedCoupons) {
            let code = reservedCoupons[coupon].codeMasked.replace('••••• ', '');
            const exp = reservedCoupons[coupon].endDate.replace('T00:00:00', '');
            let unixtime = new Date(exp) / 1000;
            let displayName = reservedCoupons[coupon].offerName + ' Certificate # ' + reservedCoupons[coupon].codeMasked + ' - Reserved';

            subAccounts.push({
                'Code'           : "hhonorsAmexFreeNightRewardsReserved" + code + unixtime,
                'DisplayName'    : displayName,
                'Balance'        : reservedCoupons[coupon].points,
                'Number'         : code,
                'ExpirationDate' : unixtime,
            });
        }
        browserAPI.log(JSON.stringify(subAccounts));

        data.SubAccounts = subAccounts;
        data.CombineSubAccounts = 'false';

        params.data.properties = data;
        provider.saveTemp(params.data);

        provider.setNextStep('parseZipCode', function () {
            browserAPI.log('parseZipCode: pre redirect');
            document.location.href = 'https://www.hilton.com/en/hilton-honors/guest/profile/personal-information/';
            browserAPI.log('parseZipCode: after redirect');

            setTimeout(function () {
                browserAPI.log('parseZipCode: setTimeout');
            }, 5000);
        });
    },

    getBrandGuest: function (params) {
        browserAPI.log("getBrandGuest");
        try {
            var wso2AuthToken = JSON.parse($.cookie('wso2AuthToken'));
        } catch (e) {
            browserAPI.log('get brand guest failed: token or guest id not found');
            return [];
        }
        if (
            wso2AuthToken === null
            || typeof wso2AuthToken !== 'object'
            || typeof wso2AuthToken.accessToken === undefined
            || typeof wso2AuthToken.guestId === undefined
        ) {
            browserAPI.log('get brand guest failed: token or guest id not found');
            return [];
        }

        var headers = {
            'Accept': '*/*',
            'Authorization': 'Bearer ' + wso2AuthToken.accessToken,
            'Content-Type': 'application/json'
        };
        var payload = {"operationName":"guest_hotel_MyAccount","variables":{"guestId":wso2AuthToken.guestId,"language":"en"},"query":"query guest_hotel_MyAccount($guestId: BigInt!, $language: String!) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    personalinfo {\n      name {\n        firstName @toTitleCase\n        __typename\n      }\n      emails {\n        validated\n        __typename\n      }\n      phones {\n        validated\n        __typename\n      }\n      hasUSAddress: hasAddressWithCountry(countryCodes: [\"US\"])\n      __typename\n    }\n    hhonors {\n      hhonorsNumber\n      isTeamMember\n      isLifetimeDiamond\n      isOwner\n      isOwnerHGV\n      isAmexCardHolder\n      summary {\n        tier\n        tierName\n        nextTier\n        requalTier\n        pointsExpiration\n        tierExpiration\n        nextTierName\n        totalPointsFmt\n        qualifiedNights\n        qualifiedNightsNext\n        qualifiedPoints\n        qualifiedPointsNext\n        qualifiedPointsFmt\n        qualifiedPointsNextFmt\n        qualifiedNightsMaint\n        rolledOverNights\n        showRequalMaintainMessage\n        showRequalDowngradeMessage\n        milestones {\n          applicableNights\n          bonusPoints\n          bonusPointsFmt\n          bonusPointsNext\n          bonusPointsNextFmt\n          maxBonusPoints\n          maxBonusPointsFmt\n          maxNights\n          nightsNext\n          showMilestoneBonusMessage\n          __typename\n        }\n        __typename\n      }\n      amexCoupons {\n        _available {\n          totalSize\n          __typename\n        }\n        _held {\n          totalSize\n          __typename\n        }\n        _used {\n          totalSize\n          __typename\n        }\n        available(sort: {by: startDate, order: asc}) {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        held {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        used {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment GuestHHonorsAmexCoupon on GuestHHonorsDetailCoupon {\n  checkInDate\n  checkOutDate\n  codeMasked\n  checkOutDateFmt(language: $language)\n  endDate\n  endDateFmt(language: $language)\n  location\n  numberOfNights\n  offerName\n  points\n  rewardType\n  startDate\n  status\n  hotel {\n    name\n    images {\n      master(imageVariant: honorsPropertyImageThumbnail) {\n        url\n        altText\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"};
        $.ajax({
            async: false,
            type: 'POST',
            url: 'https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount',
            headers: headers,
            data: JSON.stringify(payload),
            success: function (response) {
                browserAPI.log('success');
                params.data.brandGuest = response;
            },
            error: function (response) {
                browserAPI.log('error');
                browserAPI.log(response.statusText);
                params.data.brandGuest = null;
                browserAPI.log('failed to load brandGuest');
            }
        });
        provider.saveTemp(params.data);

        return params.data.brandGuest;
    },

    getFreeNightRewards: function (params) {
        browserAPI.log("getFreeNightRewards");
        try {
            var wso2AuthToken = JSON.parse($.cookie('wso2AuthToken'));
        } catch (e) {
            browserAPI.log('get Free Night Rewards: token or guest id not found');
            return [];
        }
        if (
            wso2AuthToken === null
            || typeof wso2AuthToken !== 'object'
            || wso2AuthToken.accessToken === undefined
            || wso2AuthToken.guestId === undefined
        ) {
            browserAPI.log('get Free Night Rewards: token or guest id not found');
            return [];
        }

        const headers = {
            'Accept'       : '*/*',
            'Authorization': 'Bearer ' + wso2AuthToken.accessToken,
            'Content-Type' : 'application/json'
        };
        const payload = {"operationName":"guest_hotel_MyAccount","variables":{"guestId": wso2AuthToken.guestId,"language":"en"},"query":"query guest_hotel_MyAccount($guestId: BigInt!, $language: String!) {\n  guest(guestId: $guestId, language: $language) {\n    id: guestId\n    guestId\n    personalinfo {\n      name {\n        firstName @toTitleCase\n        __typename\n      }\n      emails {\n        validated\n        __typename\n      }\n      phones {\n        validated\n        __typename\n      }\n      hasUSAddress: hasAddressWithCountry(countryCodes: [\"US\"])\n      __typename\n    }\n    hhonors {\n      hhonorsNumber\n      isTeamMember\n      isLifetimeDiamond\n      isOwner\n      isOwnerHGV\n      isAmexCardHolder\n      summary {\n        tier\n        tierName\n        nextTier\n        requalTier\n        pointsExpiration\n        tierExpiration\n        nextTierName\n        totalPointsFmt\n        qualifiedNights\n        qualifiedNightsNext\n        qualifiedPoints\n        qualifiedPointsNext\n        qualifiedPointsFmt\n        qualifiedPointsNextFmt\n        qualifiedNightsMaint\n        rolledOverNights\n        showRequalMaintainMessage\n        showRequalDowngradeMessage\n        milestones {\n          applicableNights\n          bonusPoints\n          bonusPointsFmt\n          bonusPointsNext\n          bonusPointsNextFmt\n          maxBonusPoints\n          maxBonusPointsFmt\n          maxNights\n          nightsNext\n          showMilestoneBonusMessage\n          __typename\n        }\n        __typename\n      }\n      amexCoupons {\n        _available {\n          totalSize\n          __typename\n        }\n        _held {\n          totalSize\n          __typename\n        }\n        _used {\n          totalSize\n          __typename\n        }\n        available(sort: {by: startDate, order: asc}) {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        held {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        used {\n          ...GuestHHonorsAmexCoupon\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment GuestHHonorsAmexCoupon on GuestHHonorsDetailCoupon {\n  checkInDate\n  checkOutDate\n  code\n  codeMasked\n  confirmationNumber\n  checkOutDateFmt(language: $language)\n  endDate\n  endDateFmt(language: $language)\n  location\n  numberOfNights\n  offerCode\n  offerName\n  points\n  rewardType\n  startDate\n  status\n  hotel {\n    name\n    images {\n      master(imageVariant: honorsPropertyImageThumbnail) {\n        url\n        altText\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"};
        $.ajax({
            async: false,
            type: 'POST',
            url: 'https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_hotel_MyAccount',
            headers: headers,
            data: JSON.stringify(payload),
            success: function (response) {
                browserAPI.log('success');
                params.data.freeNightResponse = response;
            },
            error: function (response) {
                browserAPI.log('error');
                browserAPI.log(response.statusText);
                params.data.freeNightResponse = null;
                browserAPI.log('failed to load Free Night Rewards');
            }
        });
        provider.saveTemp(params.data);

        return params.data.freeNightResponse;
    },

    parseLastActivity: function (params) {
        browserAPI.log("parseLastActivity");
        let guestActivitiesSummary = plugin.getHistory(params);
        browserAPI.log('history: ' + guestActivitiesSummary.length + ' transactions were found');
        let dec31 = 1672444800;// December 31, 2022
        let hasCanceled = false;
        if (typeof params.data.properties.Status !== undefined
            && params.data.properties.Status !== 'Lifetime Diamond') {
            $.each(guestActivitiesSummary, function (_, transaction) {
                if (transaction.departureDate &&
                    ['cancelled', 'upcoming'].indexOf(transaction.guestActivityType) === -1
                ) {
                    let departureDate = transaction.departureDate;
                    params.data.properties.LastActivity = departureDate;
                    browserAPI.log("LastActivity: " + params.data.properties.LastActivity);
                    const date = new Date(departureDate.replace(/-/g, '/') + ' UTC');
                    date.setMonth(date.getMonth() + 24);
                    const d = Math.floor(date.getTime() / 1000);
                    if (
                        d
                        && transaction.totalPoints !== 0
                    ) {
                        params.data.properties.AccountExpirationDate = d;
                        if (d < dec31) {
                            browserAPI.log('extending exp date by provider rules');
                            params.data.properties.AccountExpirationDate = dec31;
                        }

                        return false;
                    }
                }
                if (transaction.departureDate && ['cancelled'].indexOf(transaction.guestActivityType) !== -1) {
                    hasCanceled = true;
                }
            });
        }
        if (typeof params.data.properties.Status !== undefined
            && params.data.properties.Status === 'Lifetime Diamond') {
            browserAPI.log("expiration date set to never");
            params.data.properties.AccountExpirationDate = 'false';
            params.data.properties.AccountExpirationWarning = 'do not expire with elite status';
            browserAPI.log("clear the old expiration date");
            params.data.properties.ClearExpirationDate = 'Y';
        }
        /*
        if (
            (
                guestActivitiesSummary.length === 0
                || (typeof (params.data.properties.AccountExpirationDate) == 'undefined' && hasCanceled === true)
            )
            && $('span:contains("No results found."):visible').length
            && Math.round(new Date().getTime()/1000) < dec31
        ) {
            browserAPI.log('no history, extending exp date by provider rules');
            params.data.properties.AccountExpirationDate = dec31;
        }
        */

        browserAPI.log('parseLastActivity: saveProperties');

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        plugin.parseHistory(params);
    },

    parseComplete: function (params) {
        browserAPI.log("parseComplete");
        params.account.properties = params.data.properties;
        // console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        browserAPI.log(">>> complete");
        provider.complete();
    },

    parseZipCode: function(params) {
        browserAPI.log("parseZipCode");
        try {
            var wso2AuthToken = JSON.parse($.cookie('wso2AuthToken'));
        } catch (e) {
            browserAPI.log('get history failed: token or guest id not found');
            return [];
        }
        if (
            wso2AuthToken === null
            || typeof wso2AuthToken !== 'object'
            || wso2AuthToken.accessToken === undefined
            || wso2AuthToken.guestId === undefined
        ) {
            browserAPI.log('get history failed: token or guest id not found');
        } else {
            let headers = {
                'Accept': '*/*',
                'Authorization': 'Bearer ' + wso2AuthToken.accessToken,
                'Content-Type': 'application/json'
            };
            let payload = {"operationName":"guest_languages","variables":{"guestId":wso2AuthToken.guestId,"language":"en"},"query":"query guest_languages($language: String!, $guestId: BigInt!) {\n  guest(guestId: $guestId, language: $language) {\n    ...GuestPersonalInfo\n    ...HonorsInfo\n    ...Preferences\n    ...GuestTravelAccounts\n    __typename\n  }\n  languages(language: $language, sort: [{by: languageName}]) {\n    __typename\n    languageCode\n    languageName\n  }\n}\n\nfragment GuestPersonalInfo on Guest {\n  personalinfo {\n    __typename\n    name {\n      __typename\n      nameFmt @toTitleCase\n    }\n    paymentMethods(sort: [{by: preferred}]) {\n      ...PaymentMethods\n      __typename\n    }\n    phones(sort: [{by: preferred}]) {\n      ...PhoneNumbers\n      __typename\n    }\n    addresses {\n      ...Address\n      __typename\n    }\n    emails(sort: [{by: preferred}]) {\n      ...Email\n      __typename\n    }\n  }\n  __typename\n}\n\nfragment Address on GuestAddress {\n  __typename\n  addressId\n  addressLine1 @toTitleCase\n  addressLine2 @toTitleCase\n  addressLine3 @toTitleCase\n  addressType @toTitleCase\n  city @toTitleCase\n  state @toSentenceCase\n  postalCode\n  country\n  countryName\n  preferred\n  company @toTitleCase\n}\n\nfragment Email on GuestEmail {\n  __typename\n  emailId\n  emailAddressMasked\n  preferred\n  validated\n}\n\nfragment PhoneNumbers on GuestPhone {\n  __typename\n  phoneId\n  phoneType\n  phoneExtension\n  phoneNumberMasked(format: masked)\n  preferred\n  validated\n  phoneNumber2FAStatus\n  phoneCountry\n}\n\nfragment PaymentMethods on GuestPaymentMethod {\n  __typename\n  paymentId\n  cardCode\n  cardName\n  cardExpireDate\n  lastFour: cardNumberMasked(format: lastFour)\n  cardNumberMasked: cardNumberMasked(format: masked)\n  cardExpireDateMed: cardExpireDateFmt(format: \"medium\")\n  cardExpireDateLong: cardExpireDateFmt(format: \"long\")\n  expired\n  preferred\n}\n\nfragment HonorsInfo on Guest {\n  __typename\n  hhonors {\n    __typename\n    hhonorsNumber\n    summary {\n      tierName\n      __typename\n    }\n  }\n}\n\nfragment Preferences on Guest {\n  __typename\n  preferences {\n    __typename\n    personalizations {\n      __typename\n      preferredLanguage\n    }\n  }\n}\n\nfragment GuestTravelAccounts on Guest {\n  __typename\n  travelAccounts {\n    ...TravelAccounts\n    __typename\n  }\n}\n\nfragment TravelAccounts on GuestTravelAccounts {\n  __typename\n  corporateAccount\n  travelAgentNumber\n  unlimitedBudgetNumber\n  aarpNumber\n  aaaNumber\n  aaaInternationalNumber\n  travelAgentNumber\n  governmentMilitary\n}\n"};
            $.ajax({
                async: false,
                type: 'POST',
                url: 'https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_languages',
                headers: headers,
                data: JSON.stringify(payload),
                success: function (response) {
                    browserAPI.log('success');
                    let addresses = plugin.objectVal(response, ['data', 'guest', 'personalinfo', 'addresses'], []);
                    $.each(addresses, function (_, address) {
                        if (address.addressType != 'home' || address.preferred != true) {
                            browserAPI.log("Skip Address -> " + address.addressType);
                            return;
                        }

                        if (typeof (address.postalCode) != 'undefined' && address.postalCode) {
                            params.data.properties.ZipCode = address.postalCode;
                            browserAPI.log("ZipCode: " + params.data.properties.ZipCode );
                        } else {
                            browserAPI.log("ZipCode not found");
                        }

                        let parsedAddress = '';
                        if (typeof (address.addressLine1) != 'undefined' && address.addressLine1) {
                            parsedAddress = parsedAddress + address.addressLine1;
                        }
                        if (typeof (address.addressLine2) != 'undefined' && address.addressLine2) {
                            parsedAddress = parsedAddress + ', ' + address.addressLine2;
                        }
                        if (typeof (address.addressLine3) != 'undefined' && address.addressLine3) {
                            parsedAddress = parsedAddress + ', ' + address.addressLine3;
                        }
                        if (typeof (address.city) != 'undefined' && address.city) {
                            parsedAddress = parsedAddress + ', ' + address.city;
                        }
                        if (typeof (address.state) != 'undefined' && address.state) {
                            parsedAddress = parsedAddress + ', ' + address.state;
                        }
                        if (typeof (address.postalCode) != 'undefined' && address.postalCode) {
                            parsedAddress = parsedAddress + ', ' + address.postalCode;
                        }
                        if (typeof (address.countryName) != 'undefined' && address.countryName) {
                            parsedAddress = parsedAddress + ', ' + address.countryName;
                        }
                        parsedAddress = parsedAddress.replace(/(, ){2,}/g, '');
                        params.data.properties.ParsedAddress = parsedAddress;
                        browserAPI.log("ParsedAddress: " + params.data.properties.ParsedAddress );
                    });

                    // Name
                    let name = util.findRegExp(JSON.stringify(response), /GuestName","nameFmt":"(.+?)"/i);
                    if (name) {
                        name = util.beautifulName(name);
                        browserAPI.log("Set Name: " + name );
                        params.data.properties.Name = name;
                    }
                },
                error: function (response)  {
                    browserAPI.log('error');
                    browserAPI.log(response.statusText);
                    browserAPI.log('failed to load Profile Info');
                }
            });
        }

        provider.saveProperties(params.account.properties);

        // Parsing LastActivity
        provider.setNextStep('parseLastActivity', function () {
            document.location.href = 'https://www.hilton.com/en/hilton-honors/guest/activity/';
        });
    },

    getTime: function (dateString) {
        var date = new Date();
        if (typeof dateString === 'string') {
            date = new Date(dateString.replace(/-/g, '/') + ' UTC');
        }
        date.setMinutes(0);
        return Math.floor(date.getTime() / 1000);
    },

    getHistory: function (params) {
        browserAPI.log("getHistory");
        if (params.data.guestActivitiesSummary && params.data.guestActivitiesSummary.length > 0) {
            return params.data.guestActivitiesSummary;
        }
        try {
            var wso2AuthToken = JSON.parse($.cookie('wso2AuthToken'));
        } catch (e) {
            browserAPI.log('get history failed: token or guest id not found');
            return [];
        }
        if (
            wso2AuthToken === null
            || typeof wso2AuthToken !== 'object'
            || wso2AuthToken.accessToken === undefined
            || wso2AuthToken.guestId === undefined
        ) {
            browserAPI.log('get history failed: token or guest id not found');
            return [];
        }

        var headers = {
            'Accept': '*/*',
            'Authorization': 'Bearer ' + wso2AuthToken.accessToken,
            'Content-Type': 'application/json'
        };
        var formatDate = function (date) {
            return (
                date.getFullYear() + '-' +
                ('0' + (date.getMonth() + 1)).slice(-2) + '-' +
                ('0' + date.getDate()).slice(-2)
            );
        };
        var startDate = new Date();
        startDate.setFullYear(startDate.getFullYear() - 1);
        startDate = formatDate(startDate);
        var endDate = new Date();
        endDate.setFullYear(endDate.getFullYear() + 1);
        endDate = formatDate(endDate);
        var payload = { "operationName": "guest_guestActivitySummaryOptions", "variables": { "guestId": wso2AuthToken.guestId, "language": "en", "startDate": startDate, "endDate": endDate }, "query": "query guest_guestActivitySummaryOptions($guestId: BigInt!, $language: String!, $startDate: String!, $endDate: String!, $guestActivityTypes: [GuestActivityType]) {\n  guest(guestId: $guestId, language: $language) {\n    activitySummaryOptions(input: {groupMultiRoomStays: true, startDate: $startDate, endDate: $endDate, guestActivityTypes: $guestActivityTypes}) {\n      guestActivitiesSummary {\n        ...StayActivitySummary\n        roomDetails {\n          ...StayRoomDetails\n          __typename\n        }\n        transactions {\n          ...StayTransaction\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment StayActivitySummary on StayHHonorsActivitySummary {\n  numRooms\n  stayId\n  arrivalDate\n  departureDate\n  hotelName\n  desc\n  descFmt: desc @toTitleCase\n  guestActivityType\n  checkinEligibilityStatus\n  brandCode\n  bookAgainUrl\n  checkinUrl\n  confNumber\n  cxlNumber\n  digitalKeyOfferedUrl\n  lengthOfStay\n  viewFolioUrl\n  viewOrEditReservationUrl\n  basePoints\n  basePointsFmt\n  bonusPoints\n  bonusPointsFmt\n  earnedPoints\n  earnedPointsFmt\n  totalPoints\n  totalPointsFmt\n  usedPoints\n  usedPointsFmt\n  roomNumber\n  __typename\n}\n\nfragment StayRoomDetails on StayHHonorsActivityRoomDetail {\n  basePointsFmt\n  bonusPointsFmt\n  checkinUrl\n  cxlNumber\n  checkinEligibilityStatus\n  guestActivityType\n  roomSeries\n  roomNumber\n  roomTypeName\n  roomTypeNameFmt: roomTypeName @truncate(byWords: true, length: 3)\n  totalPointsFmt\n  usedPointsFmt\n  viewFolioUrl\n  bookAgainUrl\n  transactions {\n    ...StayTransaction\n    __typename\n  }\n  __typename\n}\n\nfragment StayTransaction on StayHHonorsTransaction {\n  transactionId\n  transactionType\n  partnerName\n  baseEarningOption\n  guestActivityPointsType\n  description\n  descriptionFmt: description @toTitleCase\n  basePoints\n  basePointsFmt\n  bonusPoints\n  bonusPointsFmt\n  earnedPoints\n  earnedPointsFmt\n  usedPoints\n  usedPointsFmt\n  __typename\n}\n" };
        $.ajax({
            async: false,
            type: 'POST',
            url: 'https://www.hilton.com/graphql/customer?appName=dx-guests-ui&operationName=guest_guestActivitySummaryOptions',
            headers: headers,
            data: JSON.stringify(payload),
            success: function (response) {
                browserAPI.log('success');
                params.data.guestActivitiesSummary = plugin.objectVal(response, ['data', 'guest', 'activitySummaryOptions', 'guestActivitiesSummary'], []);
            },
            error: function (response)  {
                browserAPI.log('error');
                browserAPI.log(response.statusText);
                params.data.guestActivitiesSummary = [];
                browserAPI.log('failed to load guestActivitiesSummary');
            }
        });
        provider.saveTemp(params.data);

        return params.data.guestActivitiesSummary;
    },

    objectVal: function (obj, keys, default_ = null) {
        if (!obj) {
            return default_;
        }
        var res = obj;
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (res instanceof Object && res[key] !== undefined) {
                res = res[key];
            } else {
                console.log('Invalid keys:');
                console.log(keys);
                return default_;
            }
        }
        if (typeof res === 'string') {
            res = res.trim();
        }

        return res;
    },

    parseHistory: function (params) {
        browserAPI.log("parseHistory");
        var startDate = params.account.historyStartDate;
        browserAPI.log("historyStartDate: " + startDate);
        if (startDate > 0) {
            let newStartDate = new Date(startDate * 1000);
            newStartDate.setDate(newStartDate.getDate() - 4);
            startDate = newStartDate / 1000;
            browserAPI.log('>> [set historyStartDate date -4 days]: ' + startDate);
        }


        var guestActivitiesSummary = plugin.getHistory(params);
        var result = [];
        $.each(guestActivitiesSummary, function (_, transaction) {
            var dateStr = transaction.arrivalDate;
            var postDate = plugin.getTime(dateStr);
            if (startDate && postDate < startDate) {
                browserAPI.log('break at date ' + dateStr);
                return false; // break
            }
            var row = {};
            row.Date = postDate;
            row['Check-out Date'] = plugin.getTime(transaction.departureDate);
            row.Description = transaction.descFmt;

            var parseDetails = true;
            var skipTransaction = false;
            switch (transaction.guestActivityType) {
                case 'past':
                    row.Type = 'Points activity';
                    break;
                case 'cancelled':
                    parseDetails = false;
                    row.Type = 'Cancellation ' + transaction.cxlNumber;
                    var yesterday = Math.floor(Date.now() / 1000) - 24 * 60 * 60;
                    if (row['Check-out Date'] > yesterday) {
                        skipTransaction = true;
                    }
                    break;
                case 'other':
                    parseDetails = false;
                    row.Date = row['Check-out Date'];
                    delete row['Check-out Date'];
                    row.Type = 'Points earned';
                    if (transaction.totalPoints < 0) {
                        row.Type = 'Points used';
                    }
                    break;
                case 'upcoming':
                    browserAPI.log('skipping upcoming reservation: ' + row.Date + ' / ' + row.Description);
                    skipTransaction = true;
                    break;
                default:
                    browserAPI.log('new history type was found ' + transaction.guestActivityType);
                    break;
            }
            if (skipTransaction) {
                return true; // continue
            }
            row['Points Earned'] = transaction.totalPointsFmt;
            result.push(row);

            if (parseDetails) {
                transactionDetails = transaction.transactions || [];
                $.each(transactionDetails, function (_, transactionDetail) {
                    row = {};
                    row.Date = postDate;
                    row.Type = 'Details';
                    row.Description = transactionDetail.descriptionFmt;
                    if (transactionDetail.guestActivityPointsType === 'pointsUsed') {
                        row.Points = transactionDetail.usedPointsFmt;
                    } else {
                        row.Points = transactionDetail.basePointsFmt;
                    }
                    row.Bonus = transactionDetail.bonusPointsFmt;
                    result.push(row);
                });

                var roomDetails = transaction.roomDetails || [];
                $.each(roomDetails, function (i, room) {
                    var roomIndex = i + 1;
                    transactionDetails = room.transactions || [];
                    $.each(transactionDetails, function (_, transactionDetail) {
                        row = {};
                        row.Date = postDate;
                        row.Type = 'Details';
                        row.Description = 'Room ' + roomIndex + ':' + ' ' + transactionDetail.descriptionFmt;
                        if (transactionDetail.guestActivityPointsType === 'pointsUsed') {
                            row.Points = transactionDetail.usedPointsFmt;
                        } else {
                            row.Points = transactionDetail.basePointsFmt;
                        }
                        row.Bonus = transactionDetail.bonusPointsFmt;
                        result.push(row);
                    });
                });
            }
        });
        params.data.properties.HistoryRows = result;

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        if (typeof (params.account.parseItineraries) == 'boolean' &&
            params.account.parseItineraries
        ) {
            provider.setNextStep('parseItineraries', function () {
                var link = $('a:contains("Activity"):visible').filter(function () {
                    return $(this).text() === 'Activity';
                });
                if (link.length === 1)
                    document.location.href = link.attr('href');
                else
                    document.location.href = 'https://secure3.hilton.com/en_US/hh/customer/account/reservations.htm';
                util.waitFor({
                    selector: 'div#main>div:nth(2)>section:nth(1) span[aria-live="polite"]:not(:contains("Loading..."))',
                    success: function () {
                        plugin.parseItineraries(params);
                    },
                    fail: function () {
                        plugin.parseItineraries(params);
                    },
                    timeout: 30
                });

            });
        } else {
            provider.complete();
        }
    },

    parseItineraries: function (params) {
        browserAPI.log('parseItineraries');
        params.data.Reservations = [];
        var activities = plugin.getHistory(params);

        var upcoming = [];
        var cancelled = [];
        var past = [];
        $.each(activities, function (_, activity) {
            var type = activity.guestActivityType;
            if (type === 'upcoming') {
                upcoming.push(activity);
            } else if (type === 'cancelled') {
                cancelled.push(activity);
            } else if (type === 'past') {
                past.push(activity);
            } else if (type === 'other') {
                browserAPI.log('Skipping type other');
            } else {
                browserAPI.log('New type: ' + type);
            }
        });

        browserAPI.log('Found ' + upcoming.length + ' upcoming itineraries');
        browserAPI.log('Found ' + cancelled.length + ' cancelled itineraries');
        browserAPI.log('Found ' + past.length + ' past itineraries');

        params.data.cntSkipped = 0;
        $.each(upcoming, function (_, activity) {
            if (params.data.Reservations.length === 100)
                return false;
            var reservationData = plugin.getReservationData(activity);
            if (reservationData) {
                plugin.parseItinerary(params, reservationData);
            } else {
                plugin.parseMinimalItinerary(params, activity, true);
            }
        });
        var withDetails = cancelled.length <= 20;
        $.each(cancelled, function (_, activity) {
            plugin.parseMinimalItinerary(params, activity, withDetails);
        });
        if (
            upcoming.length === 0 && cancelled.length === params.data.cntSkipped
            && (
                $('p:contains("We couldn\'t find any upcoming stays. Adjust your filters to view all activity"):visible').length > 0
                || $('h3>span:contains("No results found"):visible').length > 0
            )
        ) {
            browserAPI.log("no Itineraries");
            params.account.properties.Reservations = [{NoItineraries: true}];
        } else {
            browserAPI.log("[Current URL]: " + document.location.href);// debug
            params.account.properties.Reservations = params.data.Reservations;
        }
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    getReservationData: function (activity) {
        browserAPI.log('getReservationData');
        var confNumber = activity.confNumber;
        // var lastName = util.findRegExp(activity.viewOrEditReservationUrl || '', /lastNameOrCCLastFourDigits=(.+)/);
        var lastName = util.findRegExp(activity.viewOrEditReservationUrl || '', /&lastName=(.+)/);
        if (!lastName) {
            browserAPI.log('lastName is missing');
            return null;
        }
        var arrivalDate = activity.arrivalDate;
        try {
            var wso2AuthToken = JSON.parse($.cookie('wso2AuthToken'));
        } catch (e) {
            browserAPI.log('guestId missing');
            return null;
        }
        var guestId = wso2AuthToken ? wso2AuthToken.guestId : null;
        if (!guestId) {
            browserAPI.log('guestId missing');
            return null;
        }
        var token = wso2AuthToken.accessToken;
        if (!token) {
            browserAPI.log('auth token missing');
            return null;
        }

        var headers = {
            'Accept': '*/*',
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        };

        var payload = {"operationName":"reservation","variables":{"confNumber":confNumber,"language":"en","guestId":guestId,"lastName":lastName,"arrivalDate":arrivalDate},"query":"query reservation($confNumber: String!, $language: String!, $guestId: BigInt, $lastName: String!, $arrivalDate: String!) {\n  reservation(\n    confNumber: $confNumber\n    language: $language\n    authInput: {guestId: $guestId, lastName: $lastName, arrivalDate: $arrivalDate}\n  ) {\n    ...RESERVATION_FRAGMENT\n    __typename\n  }\n}\n\nfragment RESERVATION_FRAGMENT on Reservation {\n  addOnsResModifyEligible\n  confNumber\n  arrivalDate\n  departureDate\n  cancelEligible\n  modifyEligible\n  cxlNumber\n  restricted\n  adjoiningRoomStay\n  adjoiningRoomsFailure\n  scaRequired\n  autoUpgradedStay\n  showAutoUpgradeIndicator\n  specialRateOptions {\n    corporateId\n    groupCode\n    hhonors\n    pnd\n    promoCode\n    travelAgent\n    familyAndFriends\n    teamMember\n    owner\n    ownerHGV\n    __typename\n  }\n  clientAccounts {\n    clientId\n    clientType\n    clientName\n    __typename\n  }\n  comments {\n    generalInfo\n    __typename\n  }\n  disclaimer {\n    diamond48\n    fullPrePayNonRefundable\n    hgfConfirmation\n    hgvMaxTermsAndConditions\n    hhonorsCancellationCharges\n    hhonorsPointsDeduction\n    hhonorsPrintedConfirmation\n    lengthOfStay\n    rightToCancel\n    totalRate\n    teamMemberEligibility\n    vatCharge\n    __typename\n  }\n  certificates {\n    totalPoints\n    totalPointsFmt\n    __typename\n  }\n  cost {\n    currency {\n      currencyCode\n      currencySymbol\n      description\n      __typename\n    }\n    roomRevUSD: totalAmountBeforeTax(currencyCode: \"USD\")\n    totalAddOnsAmount\n    totalAddOnsAmountFmt\n    totalAmountBeforeTax\n    totalAmountAfterTaxFmt: guestTotalCostAfterTaxFmt\n    totalAmountAfterTax: guestTotalCostAfterTax\n    totalAmountBeforeTaxFmt\n    totalServiceCharges\n    totalServiceChargesFmt\n    totalTaxes\n    totalTaxesFmt\n    __typename\n  }\n  foodAndBeverageCreditBenefit {\n    description\n    heading\n    linkLabel\n    linkUrl\n    __typename\n  }\n  guarantee {\n    cxlPolicyCode\n    cxlPolicyDesc\n    guarPolicyCode\n    guarPolicyDesc\n    guarMethodCode\n    taxDisclaimers {\n      text\n      title\n      __typename\n    }\n    disclaimer {\n      legal\n      __typename\n    }\n    paymentCard {\n      cardCode\n      cardName\n      cardNumber\n      cardExpireDate\n      expireDate: cardExpireDateFmt(format: \"MMM yyyy\")\n      expireDateFull: cardExpireDateFmt(format: \"MMMM yyyy\")\n      expired\n      policy {\n        bankValidationMsg\n        __typename\n      }\n      __typename\n    }\n    deposit {\n      amount\n      __typename\n    }\n    taxDisclaimers {\n      text\n      title\n      __typename\n    }\n    __typename\n  }\n  guest {\n    guestId\n    tier\n    name {\n      firstName\n      lastName\n      nameFmt\n      __typename\n    }\n    emails {\n      emailAddress\n      emailType\n      __typename\n    }\n    addresses {\n      addressLine1\n      addressLine2\n      city\n      country\n      state\n      postalCode\n      addressFmt\n      addressType\n      __typename\n    }\n    hhonorsNumber\n    phones {\n      phoneNumber\n      phoneType\n      __typename\n    }\n    __typename\n  }\n  propCode\n  nor1Upgrade(provider: \"DOHWR\") {\n    content {\n      button\n      description\n      firstName\n      title\n      __typename\n    }\n    offerLink\n    requested\n    success\n    __typename\n  }\n  notifications {\n    subType\n    text\n    type\n    __typename\n  }\n  requests {\n    specialRequests {\n      pets\n      servicePets\n      __typename\n    }\n    __typename\n  }\n  rooms {\n    gnrNumber\n    resCreateDateFmt(format: \"yyyy-MM-dd\")\n    addOns {\n      addOnCost {\n        amountAfterTax\n        amountAfterTaxFmt\n        __typename\n      }\n      addOnDetails {\n        addOnAvailType\n        addOnDescription\n        addOnCode\n        addOnName\n        amountAfterTax\n        amountAfterTaxFmt\n        averageDailyRate\n        averageDailyRateFmt\n        categoryCode\n        counts {\n          numAddOns\n          fulfillmentDate\n          rate\n          rateFmt\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    additionalNames {\n      firstName\n      lastName\n      __typename\n    }\n    certificates {\n      certNumber\n      totalPoints\n      totalPointsFmt\n      __typename\n    }\n    numAdults\n    numChildren\n    childAges\n    autoUpgradedStay\n    isStayUpsell\n    isStayUpsellOverAutoUpgrade\n    priorRoomType {\n      roomTypeName\n      __typename\n    }\n    cost {\n      currency {\n        currencyCode\n        currencySymbol\n        description\n        __typename\n      }\n      amountAfterTax: guestTotalCostAfterTax\n      amountAfterTaxFmt: guestTotalCostAfterTaxFmt\n      amountBeforeTax\n      amountBeforeTaxFmt\n      amountBeforeTaxFmtTrunc: amountAfterTaxFmt(decimal: 0, strategy: trunc)\n      serviceChargeFeeType\n      serviceChargePeriods {\n        serviceCharges {\n          amount\n          amountFmt\n          description\n          __typename\n        }\n        __typename\n      }\n      totalServiceCharges\n      totalServiceChargesFmt\n      totalTaxes\n      totalTaxesFmt\n      rateDetails(perNight: true) {\n        effectiveDateFmt(format: \"medium\")\n        effectiveDateFmtAda: effectiveDateFmt(format: \"long\")\n        rateAmount\n        rateAmountFmt\n        rateAmountFmtTrunc: rateAmountFmt(decimal: 0, strategy: trunc)\n        __typename\n      }\n      upgradedAmount\n      upgradedAmountFmt\n      __typename\n    }\n    guarantee {\n      cxlPolicyCode\n      cxlPolicyDesc\n      guarPolicyCode\n      guarPolicyDesc\n      __typename\n    }\n    numAdults\n    numChildren\n    ratePlan {\n      confidentialRates\n      hhonorsMembershipRequired\n      advancePurchase\n      promoCode\n      disclaimer {\n        diamond48\n        fullPrePayNonRefundable\n        hhonorsCancellationCharges\n        hhonorsPointsDeduction\n        hhonorsPrintedConfirmation\n        lengthOfStay\n        rightToCancel\n        totalRate\n        __typename\n      }\n      ratePlanCode\n      ratePlanName\n      ratePlanDesc\n      specialRateType\n      serviceChargesAndTaxesIncluded\n      __typename\n    }\n    roomType {\n      adaAccessibleRoom\n      roomTypeCode\n      roomTypeName\n      roomTypeDesc\n      roomOccupancy\n      __typename\n    }\n    __typename\n  }\n  taxPeriods {\n    taxes {\n      description\n      __typename\n    }\n    __typename\n  }\n  paymentOptions {\n    cardOptions {\n      policy {\n        bankValidationMsg\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  totalNumAdults\n  totalNumChildren\n  totalNumRooms\n  unlimitedRewardsNumber\n  __typename\n}\n"};


        var data = null;
        $.ajax({
            async: false,
            type: 'POST',
            url: 'https://www.hilton.com/graphql/customer?appName=dx-reservations-ui&language=en&operationName=reservation',
            headers: headers,
            xhr: plugin.getXMLHttp,
            data: JSON.stringify(payload),
            success: function (response) {
                browserAPI.log('success');
                data = response;
            },
            error: function (response) {
                browserAPI.log('error');
                browserAPI.log(response.statusText);
                browserAPI.log('failed to load reservation data');
            }
        });
        return data;
    },

    parseItinerary: function (params, data) {
        browserAPI.log('parseItinerary');
        var reservation = plugin.objectVal(data, ['data', 'reservation']);
        if (!reservation) {
            browserAPI.log('check parse itinerary');
            return;
        }
        var departureDate = reservation.departureDate;
        var yesterday = Math.floor(Date.now() / 1000) - 24 * 60 * 60;
        var isPast = plugin.getTime(departureDate) < yesterday;
        if (isPast) {
            browserAPI.log('skipping hotel: in the past');
            params.data.cntSkipped++;
            browserAPI.log('cntSkipped' + params.data.cntSkipped);
            return;
        }

        var hotel = {};
        // ConfirmationNumber
        hotel.ConfirmationNumber = reservation.confNumber;
        browserAPI.log('ConfirmationNumber: ' + hotel.ConfirmationNumber);
        // CheckInDate
        var arrivalDate = reservation.arrivalDate;
        hotel.CheckInDate = plugin.getTime(arrivalDate);
        browserAPI.log('CheckInDate: ' + hotel.CheckInDate + ' / ' + arrivalDate);
        // CheckOutDate
        hotel.CheckOutDate = plugin.getTime(departureDate);
        browserAPI.log('CheckOutDate: ' + hotel.CheckOutDate + ' / ' + departureDate);
        // CancellationPolicy
        hotel.CancellationPolicy = plugin.objectVal(reservation, ['disclaimer', 'hhonorsCancellationCharges']);
        browserAPI.log('CancellationPolicy: ' + hotel.CancellationPolicy);
        // Total
        hotel.Total = plugin.objectVal(reservation, ['cost', 'totalAmountBeforeTax']);
        browserAPI.log('Total: ' + hotel.Total);
        // Taxes
        hotel.Taxes = plugin.objectVal(reservation, ['cost', 'totalTaxes']);
        browserAPI.log('Taxes: ' + hotel.Taxes);
        // Currency
        hotel.Currency = plugin.objectVal(reservation, ['cost', 'currency', 'currencyCode']);
        browserAPI.log('Currency: ' + hotel.Currency);
        // SpentAwards
        hotel.SpentAwards = plugin.objectVal(reservation, ['certificates', 'totalPointsFmt']);
        browserAPI.log('SpentAwards: ' + hotel.SpentAwards);
        // Rooms
        hotel.Rooms = reservation.totalNumRooms;
        browserAPI.log('Rooms: ' + hotel.Rooms);
        var roomTypes = [];
        var roomTypeDescriptions = [];
        var rates = [];
        $.each(reservation.rooms || [], function (_, roomData) {
            roomTypes.push(plugin.objectVal(roomData, ['roomType', 'roomTypeName']));
            var desc = plugin.objectVal(roomData, ['roomType', 'roomTypeDesc']);
            if (desc) {
                try {
                    roomTypeDescriptions.push($(desc).text().trim());
                } catch (e) {
                    if (e instanceof SyntaxError) {
                        roomTypeDescriptions.push(desc.text().trim());
                    }
                }
            }
            var rateDetails = plugin.objectVal(roomData, ['cost', 'rateDetails']);
            $.each(rateDetails || [], function (_, rateDetail) {
                rates.push(plugin.objectVal(rateDetail, ['rateAmountFmt']));
            });
            var cancelation = plugin.objectVal(roomData, ['guarantee', 'cxlPolicyDesc']);
            if (!hotel.CancellationPolicy && cancelation) {
                hotel.CancellationPolicy = cancelation;
                browserAPI.log('CancellationPolicy: ' + hotel.CancellationPolicy);
            }
        });
        // RoomType
        hotel.RoomType = roomTypes.join('|');
        browserAPI.log('RoomType: ' + hotel.RoomType);
        // RoomTypeDescription
        hotel.RoomTypeDescription = roomTypeDescriptions.join('|');
        browserAPI.log('RoomTypeDescription: ' + hotel.RoomTypeDescription);
        // Rate
        hotel.Rate = rates.join('|');
        browserAPI.log('Rate: ' + hotel.Rate);
        // Guests
        hotel.Guests = reservation.totalNumAdults;
        browserAPI.log('Guests: ' + hotel.Guests);
        // Kids
        if (reservation.totalNumChildren) {
            hotel.Kids = reservation.totalNumChildren;
            browserAPI.log('Kids: ' + hotel.Kids);
        }
        // hotel data
        var propCode = reservation.propCode;
        var hotelData = plugin.getHotelData(propCode);
        if (hotelData) {
            var skip = plugin.addHotelData(hotel, hotelData, arrivalDate, departureDate);
            if (skip) {
                browserAPI.log('Skipping hotel: the same arrival / departure dates');
                params.data.cntSkipped++;
                browserAPI.log(JSON.stringify(hotel));
                return;
            }
        }
        browserAPI.log('Parsed Hotel:');
        browserAPI.log(JSON.stringify(hotel));

        params.data.Reservations.push(hotel);
        provider.saveTemp(params.data);
    },

    getHotelData: function (propCode) {
        browserAPI.log('getHotelData');
        if (!propCode) {
            browserAPI.log('hotel property code is missing');
            return null;
        }
        try {
            var wso2AuthToken = JSON.parse($.cookie('wso2AuthToken'));
        } catch (e) {
            browserAPI.log('auth token missing');
            return null;
        }
        var token = wso2AuthToken.accessToken;
        if (!token) {
            browserAPI.log('auth token missing');
            return null;
        }

        var headers = {
            'Accept': '*/*',
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        };
        var payload = {"operationName":"brand_hotel_shopAvailOptions","variables":{"language":"en","ctyhocn":propCode},"query":"query brand_hotel_shopAvailOptions($language: String!, $ctyhocn: String!) {\n  hotel(ctyhocn: $ctyhocn, language: $language) {\n    ctyhocn\n    brandCode\n    contactInfo {\n      phoneNumber\n      __typename\n    }\n    display {\n      preOpenMsg\n      open\n      resEnabled\n      __typename\n    }\n    creditCardTypes {\n      guaranteeType\n      code\n      name\n      __typename\n    }\n    address {\n      addressFmt(format: \"stacked\")\n      countryName\n      country\n      state\n      mapCity\n      __typename\n    }\n    brand {\n      formalName\n      name\n      phone {\n        supportNumber\n        supportIntlNumber\n        __typename\n      }\n      url\n      searchOptions {\n        url\n        __typename\n      }\n      __typename\n    }\n    localization {\n      currency {\n        currencyCode\n        currencySymbol\n        description\n        __typename\n      }\n      __typename\n    }\n    overview {\n      resortFeeDisclosureDesc\n      __typename\n    }\n    name\n    propCode\n    shopAvailOptions {\n      maxArrivalDate\n      maxDepartureDate\n      minArrivalDate\n      minDepartureDate\n      maxNumOccupants\n      maxNumChildren\n      ageBasedPricing\n      adultAge\n      adjoiningRooms\n      __typename\n    }\n    hotelAmenities: amenities(filter: {groups_includes: [hotel]}) {\n      id\n      name\n      __typename\n    }\n    stayIncludesAmenities: amenities(\n      filter: {groups_includes: [stay]}\n      useBrandNames: true\n    ) {\n      id\n      name\n      __typename\n    }\n    images {\n      master(imageVariant: bookPropertyImageThumbnail) {\n        _id\n        altText\n        variants {\n          size\n          url\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    familyPolicy\n    registration {\n      checkinTimeFmt(language: $language)\n      checkoutTimeFmt(language: $language)\n      earlyCheckinText\n      __typename\n    }\n    pets {\n      description\n      __typename\n    }\n    __typename\n  }\n}\n"};
        var data = null;
        $.ajax({
            async: false,
            xhr: plugin.getXMLHttp,
            type: 'POST',
            url: 'https://www.hilton.com/graphql/customer?appName=dx-reservations-ui&ctyhocn=TYOCI&language=en&operationName=brand_hotel_shopAvailOptions',
            headers: headers,
            data: JSON.stringify(payload),
            timeout: 20000,
            success: function (response) {
                browserAPI.log('success');
                data = response;
                var hotelName = plugin.objectVal(data, ['data', 'hotel', 'name']);
                var hotelAddr = plugin.objectVal(data, ['data', 'hotel', 'address', 'addressFmt']);
                if (!hotelName || !hotelAddr)
                    browserAPI.log(JSON.stringify(data));
            },
            error: function (response) {
                browserAPI.log('error');
                browserAPI.log(response.statusText);
                browserAPI.log('failed to load hotel data');
            }
        });
        return data;
    },

    addHotelData: function (hotel, hotelData, arrivalDate, departureDate) {
        browserAPI.log('addHotelData');
        // HotelName
        hotel.HotelName = plugin.objectVal(hotelData, ['data', 'hotel', 'name']);
        browserAPI.log('HotelName: ' + hotel.HotelName);
        // Address
        hotel.Address = plugin.objectVal(hotelData, ['data', 'hotel', 'address', 'addressFmt']);
        browserAPI.log('ParsedAddress ' + hotel.Address);
        // CheckInTime
        var addTime = function(timestamp, timeString) {
            var date = new Date(timestamp * 1000);
            if (!date) {
                return;
            }
            var m = timeString.match(/^(\d+):(\d+)\s*(p?)/i);
            if (!m) {
                return null;
            }
            var hours = parseInt(m[1]) + ((parseInt(m[1]) < 12 && m[3]) ? 12 : 0);
            var minutes = parseInt(m[2]);
            if (isNaN(hours) || isNaN(minutes)) {
                return null;
            }
            date.setHours(date.getHours() + hours);
            date.setMinutes(minutes);
            var ts = Math.floor(date.getTime() / 1000);
            return ts ? ts : null;
        };
        var checkinTimeFmt = plugin.objectVal(hotelData, ['data', 'hotel', 'checkin', 'checkinTimeFmt']);
        var date = null;
        if (checkinTimeFmt) {
            date = addTime(hotel.CheckInDate, checkinTimeFmt);
            if (date) {
                hotel.CheckInDate = date;
                browserAPI.log('CheckInDate ' + hotel.CheckInDate + ' / ' + checkinTimeFmt);
            }
        }
        // CheckOutTime
        var checkoutTimeFmt = plugin.objectVal(hotelData, ['data', 'hotel', 'checkin', 'checkoutTimeFmt']);
        if (checkoutTimeFmt) {
            date = addTime(hotel.CheckOutDate, checkoutTimeFmt);
            if (date) {
                hotel.CheckOutDate = date;
                browserAPI.log('CheckOutDate ' + hotel.CheckOutDate + ' / ' + checkoutTimeFmt);
            }
        }
        if (arrivalDate === departureDate && hotel.CheckOutDate < hotel.CheckInDate) {
            return true;
        }
        return false;
    },

    parseMinimalItinerary: function (params, activity, withDetails) {
        browserAPI.log('parseMinimalItinerary');
        var hotel = {};
        if (!activity) {
            browserAPI.log('check parse minimal itinerary');
            return;
        }
        var departureDate = activity.departureDate;
        var yesterday = Math.floor(Date.now() / 1000) - 24 * 60 * 60;
        var isPast = plugin.getTime(departureDate) < yesterday;
        if (isPast) {
            browserAPI.log('skipping hotel: in the past');
            params.data.cntSkipped++;
            browserAPI.log('cntSkipped ' + params.data.cntSkipped);
            return;
        }
        var arrivalDate = activity.arrivalDate;
        /*
        if (arrivalDate === departureDate) {
            browserAPI.log('skipping hotel: the same arrival / departure dates');
            return;
        }
        */
        var propCode = util.findRegExp(activity.bookAgainUrl || '', /ctyhocn=(\w+)/);
        if (!propCode && activity.guestActivityType !== 'cancelled') {
            browserAPI.log('skipping hotel: property code is missing');
            return;
        }
        // ConfirmationNumber
        hotel.ConfirmationNumber = activity.confNumber;
        browserAPI.log('ConfirmationNumber: ' + hotel.ConfirmationNumber);
        hotel.CheckInDate = plugin.getTime(arrivalDate);
        browserAPI.log('CheckInDate: ' + hotel.CheckInDate + ' / ' + arrivalDate);
        // CheckOutDate
        hotel.CheckOutDate = plugin.getTime(departureDate);
        browserAPI.log('CheckOutDate: ' + hotel.CheckOutDate + ' / ' + departureDate);
        // SpentAwards
        var usedPoints = parseInt(activity.usedPoints);
        if (usedPoints) {
            hotel.SpentAwards = activity.usedPointsFmt;
        }
        // hotel data
        if (activity.guestActivityType === 'cancelled') {
            hotel.Cancelled = true;

        }
        if (withDetails) {
            var hotelData = plugin.getHotelData(propCode);
            if (hotelData) {
                var skip = plugin.addHotelData(hotel, hotelData, arrivalDate, departureDate);
                if (skip) {
                    browserAPI.log('Skipping hotel: the same arrival / departure dates');
                    params.data.cntSkipped++;
                    browserAPI.log(JSON.stringify(hotel));
                    return;
                }
            }
        }
        browserAPI.log('Parsed Hotel:');
        browserAPI.log(JSON.stringify(hotel));

        params.data.Reservations.push(hotel);
        provider.saveTemp(params.data);
    },

    unionArray: function ( elem, separator, unique ){
        // $.map not working in IE 8, so iterating through items
        var result = [];
        for (var i = 0; i < elem.length; i++) {
            var text = util.trim(elem.eq(i).text());
            if (text != "" && (!unique || result.indexOf(text) == -1))
                result.push(text);
        }

        return result.join( separator );
    }
};