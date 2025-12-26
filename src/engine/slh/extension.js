var plugin = {

    hosts: {
        'www.slh.com': true,
        'slh.com': true,
        'invited.slh.com': true,
        'be.synxis.com': true,
        'slhb2c.b2clogin.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://slh.com/invited/my-invited';
    },

    loadLoginForm: function (params) {
        provider.setNextStep('login', function () {
            document.location.href = 'https://www.slh.com/login-redirect-page?returnUrl=/invited/my-invited';
        });
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    plugin.loadLoginForm(params);
                }
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
        if ($('a:contains("Sign in")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "Logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp( $('p:contains("Membership number:")').text(), /:\s*([0-9]+)$/);
        browserAPI.log("number: " + number);
            return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MembershipNumber) != 'undefined')
            && number
            && (account.properties.MembershipNumber !== '')
            && (number === account.properties.MembershipNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "Logout"]:eq(0)').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var counter = 0;
        var login = setInterval(function () {
            var form = $('form#localAccountForm');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "Email Address"]').val(params.account.login);
                form.find('input[name = "Password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button:contains("Log in")').click();
                });
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 5000);
            }
            if (counter > 7) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.error:visible');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                if (document.location.href !== 'https://invited.slh.com/')
                    document.location.href = 'https://invited.slh.com/';
                else
                    plugin.toItineraries(params);
            });
            return;
        }

        function complete() {
            var form = $('form.sc-invited-successful-registration__form:visible');
            if (form.length) {
                provider.setNextStep('itLoginComplete', function () {
                    form.find('button[type = "submit"]').click();
                });
                return;
            }
            plugin.itLoginComplete(params);
        }

        if (provider.isMobile) {
            setTimeout(function() {
                complete();
            }, 3000);
        } else {
            complete();
        }
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var link = $('a:contains("Manage my bookings"):eq(0):visible');
            if (link.length > 0) {
                provider.setNextStep('getConfNoItinerary', function(){
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var form = $('form.sign-in-container_signByEmailConfirmNo');
        if (form.length > 0) {
            // form.find('input[name = "Confirmation or Itinerary Number"]').val(params.account.properties.confirmationNumber);
            // form.find('input[name = "Email Address"]').val(params.account.login);
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input[name=\"Confirmation or Itinerary Number\"]')).onChange({target:{value:'" + params.account.properties.confirmationNumber + "'}, preventDefault:function(){}});" +
                "FindReact(document.querySelector('input[name=\"Email Address\"')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});"
            );
            provider.setNextStep('itLoginComplete', function () {
                form.find('button[type = "submit"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};