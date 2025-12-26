var plugin = {

    hosts: {'www.shopyourway.com': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        //if(!provider.isMobile)
        //     return 'https://www.shopyourway.com/secured/authentication/sign-in/form/content?returnUrl=%2Ftoday&customCode=&hae=&mnumber=&customizedAppSettings=&resetPasswordToken=&email=&emailValue=&hideOpenId=false&ignoreIfSignedIn=false';
        //else
        // The mobile page works best.
        return 'https://www.shopyourway.com/secured/m/welcome';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($($('div#authentication-app-container, form:has([type="email"])')).length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "/account/sign-out/"]:contains("Sign Out")').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('script[type="text/javascript"]').text(), /memberNumber:'(\d+)',/i);
        browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.Number !== 'undefined'
            && account.properties.Number !== ''
            && number === account.properties.Number;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "/account/sign-out/"]:contains("Sign Out")').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	login: function (params) {
        browserAPI.log("login");
        var form = $('div#authentication-app-container, form:has([type="email"])');
        if (form.length) {
            browserAPI.log("submitting saved credentials");
			$(document).bind('pageinit');
			var field = $('[type="email"]').val(params.account.login).get()[0];
			util.sendEvent(field, "input");
			util.sendEvent(field, "blur");
            var captcha = form.find('img[src *= "/captcha/image"]');
            if (captcha.length > 0) {
                browserAPI.log("waiting captcha -> " + captcha.attr('src'));
                provider.captchaMessageDesktop();
                browserAPI.log("waiting...");
                if (provider.isMobile) {
                    form.find("button").click(function () {
                        enteringPassword();
                    });
                }
                else {
                    var dataURL = captcha.attr('src');
                    browserAPI.send("awardwallet", "recognizeCaptcha", {
                        captcha: dataURL,
                        "extension": "jpg"
                    }, function(response) {
                        browserAPI.log(JSON.stringify(response));
                        if (response.success === true) {
                            browserAPI.log("Success: " + response.success);
                            provider.eval(
                                "var FindReact = function(dom) { \
                                    for (var key in dom) \
                                        if (0 == key.indexOf('__reactInternalInstance$')) { \
                                            return dom[key]; \
                                        } \
                                    return null; \
                                }; \
                                var b = FindReact(document.querySelector('input[type=number], div[class*= \"CaptchaInput--textInputContainer__\"] > input')); \
                                b.memoizedProps.onChange({target:{value:\""+response.recognized+"\"}});"
                            );
                            setTimeout(function(){
                                form.find("button").get()[0].click();
                                enteringPassword();
                            },2000);
                        }
                        else if (response.success === false) {
                            browserAPI.log("Success: " + response.success);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }
                        else {
                            browserAPI.log("Fail: " + response);
                        }
                    });
                }
            }// if (captcha.length > 0)
            else {
                browserAPI.log("captcha not found");
                form.find("button").get()[0].click();
                enteringPassword();
            }

            function enteringPassword() {
                util.waitFor({
                    selector: '[type="password"]',
                    success: function(){
                        var field = $('[type="password"]').focus().val(params.account.password).get()[0];
                        util.sendEvent(field, "input");
                        util.sendEvent(field, "blur");
                        provider.eval(
                            "var FindReact = function(dom) { \
                                for (var key in dom) \
                                    if (0 == key.indexOf('__reactInternalInstance$')) { \
                                        return dom[key]; \
                                    } \
                                return null; \
                            }; \
                            var b = FindReact(document.querySelector('[type=password]')); \
                            b.memoizedProps.onChange({target:{value:\""+params.account.password+"\"}});"
                        );
                        setTimeout(function(){
                            provider.setNextStep('checkLoginErrors', function () {
                                $('div#authentication-app-container, form:has([type="email"])').find("button").get()[0].click();
                                setTimeout(function(){
                                    plugin.checkLoginErrors();
                                },5000);
                            });
                        },2000);
                    },
                    fail: function(){
                        plugin.checkLoginErrors();
                    },
                    timeout: 5
                });
            }// function enteringPassword()
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('span[class ^= "ErrorMessage--errorText"]:not([class *= "ErrorMessage--hide__"]):visible');
        if (errors.length && util.trim(errors.text()) !== '') {
            if (/Please enter the characters above to continue/.test(errors.text()))
                provider.setError(util.errorMessages.captchaErrorMessage, true);
            else
                provider.setError(errors.text());
        }
        else {
            provider.setNextStep('isLogon', function () {
                document.location.href = 'http://www.shopyourway.com/today';
            });
        }
    },

    isLogon: function (params) {
        browserAPI.log("isLogon");
        setTimeout(function () {
            if ($('a[href *= "/account/sign-out/"]:contains("Sign Out")').length > 0)
				provider.setNextStep('loginComplete', function () {
					if(provider.isMobile)
						document.location.href = "https://www.shopyourway.com/secured/m/balance";
					else
						document.location.href = "https://www.shopyourway.com/secured/rewards/account-history#my-points";
                });
            else
                provider.setError(util.errorMessages.unknownLoginState);
        }, 3000);
    },

    loginComplete: function(params){
        if (typeof(params.account.fromPartner) == 'string') {
            // don't reopen page
            var info = { message: 'warning', reopen: false, style: 'none'};
            browserAPI.send("awardwallet", "info", info);
        }
        provider.complete();
    }
};