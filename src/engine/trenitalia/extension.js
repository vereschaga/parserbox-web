var plugin = {
    hosts: {
        'www.lefrecce.com': true,
        'www.lefrecce.it': true,
        'www.trenitalia.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.lefrecce.it/Channels.Website.WEB/#/user-area';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        const start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (counter > 0 && isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
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
        if ($('form.au-target:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('div[i18n="generic.logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('.code').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Account) != 'undefined')
            && (account.properties.Account !== '')
            && (number === account.properties.Account));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function() {
            $('div[i18n="generic.logout"]').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId === 0   ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'http://www.trenitalia.com/tcom-en/Purchase/Manage-your-ticket';
            });
            return;
        }

        const form = $('form.au-target');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }// if (form.length === 0)

        browserAPI.log("submitting saved credentials");
        // form.find('input[id = "username"]').val(params.account.login);
        // form.find('input[id = "password"]').val(params.account.password);

        provider.eval(
            "function doEvent( obj, event ) {"
            + "var event = new Event( event, {target: obj, bubbles: true} );"
            + "return obj ? obj.dispatchEvent(event) : false;"
            + "};"
            + "var el = document.querySelector('#username'); el.value = \"" + params.account.login + "\"; doEvent(el, 'input' );"
            + "var el = document.querySelector('#password'); el.value = \"" + params.account.password + "\"; doEvent(el, 'input' );"
        );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[i18n = "login.login"]').get(0).click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.alert-danger:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filtererrors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");

        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('itLoginComplete', function() {
                document.location.href = 'https://www.lefrecce.it/B2CWeb/travelsPurchased.do?method=init';
            });
            return;
        }

        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        const properties = params.account.properties.confFields;
        const form = $('form[action *= "Channels.Website.WEB/website/auth/handoff?action=searchEmailPnr"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        form.find('input[name = "pnrCode"]').val(properties.ConfNo);
        form.find('input[name = "email"]').val(properties.Email);
        provider.setNextStep('itLoginComplete', function() {
            form.find('button[type = "submit"]').click();
        });
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
