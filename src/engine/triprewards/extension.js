var plugin = {

    hosts: {
        'www.wyndhamrewards.com'   : true,
        'www.wyndhamhotelgroup.com': true,
        'wyndhamhotels.com'        : true,
        'www.wyndhamhotels.com'    : true,
        'login.wyndhamhotels.com'  : true,
    },

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.wyndhamhotels.com/wyndham-rewards/my-account';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);

            let signIn = $('div:contains("SIGN IN"):visible');
            if (signIn.length > 0) {
                browserAPI.log("not LoggedIn");
                clearInterval(start);
                provider.setNextStep('start', function () {
                    $('a:contains("Sign In")').get(0).click();
                });
                return null;
            }

            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                        //plugin.findSecurityQuestion(params);
                    else
                        plugin.logout();
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            // old form
            $('form.sign-in-form').length > 0
            // new form
            || $('main.login form').length > 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('span[data-binding="Channel.WR.MembershipID"]:eq(0)').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && (number === account.properties.AccountNumber ));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= logout]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (typeof(params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.wyndhamrewards.com/trec/consumer/home.action?variant=";
            });
            return;
        }// if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0)

        let form = $('form.sign-in-form');
        let newForm = $('main.login form');

        if (form.length === 0 && newForm.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        if (form.length > 0) {
            browserAPI.log("[old form]: submitting saved credentials");
            util.setInputValue( form.find('input[name = "login-username"]'), params.account.login);
            util.setInputValue( form.find('input[name = "login-password"]'), params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button.submit').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }// if (form.length > 0)

        if (newForm.length > 0) {
            browserAPI.log("[new form]: submitting saved credentials");
            util.setInputValue( newForm.find('input[name = "username"]'), params.account.login);
            util.setInputValue( newForm.find('input[name = "password"]'), params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                newForm.find('button[name="action"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }// if (newForm.length > 0)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.form-error:visible, span.ulp-input-error-message:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.wyndhamhotels.com/wyndham-rewards/my-account/reservations';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            const confNo = params.account.properties.confirmationNumber;
            const link = $('a.view-res-confirmation-link[data-conf="' + confNo + '"]');

            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (link.length === 0)

            provider.setNextStep('itLoginComplete', function(){
                link.get(0).click();
            });
        }, 3000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
        // open form
        $('div.WHGWR_over').get(0).click();

        const properties = params.account.properties.confFields;
        const form = $('form#WHGWR_review_Form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        form.find('input[name = "confirmNo"]').val(properties.ConfNo);
        form.find('input[name = "fname"]').val(properties.FirstName);
        form.find('input[name = "lname"]').val(properties.LastName);
        const btn = form.find('#WHGWR_review_findIt');

        if (btn.length > 0) {
            form.find('#WHGWR_review_findIt').get(0).click();
            provider.complete();
        }
    }
};
