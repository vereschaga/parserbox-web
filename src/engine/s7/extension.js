var plugin = {

    hosts: {
        'www.s7.ru'      : true,
        'myprofile.s7.ru': true
    },

    getStartingUrl: function (params) {
        return 'http://www.s7.ru/home/priority/ffpMyMiles.dot';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function (params) {
        setTimeout(function () {
            if (plugin.isLoggedIn()) {
                if (plugin.isSameAccount(params.account))
                    plugin.loginComplete(params);
                else
                    plugin.logout();
            } else
                plugin.login(params);
        }, 1000)
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form.LW__AuthorizationGroup__Form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('h3.DS__Title__root:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        let number = util.filter(util.findRegExp( $('span:contains("/")').text(), /([\d\s]+)/)).replace(/ /g, '');
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm');
        $('div.DS__Header__UserInfo button').click();
        setTimeout(function () {
            $('button.DS__Button__theme_outline').click();
        }, 500)
    },

    login: function (params) {
        browserAPI.log("login");

        if (
            params.account.accountId === 0
            && typeof params.account.itineraryAutologin === 'boolean'
            && params.account.itineraryAutologin
        ) {
            return provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.s7.ru/?_=' + new Date().getTime();
            });
        }

        let form = $('form.LW__AuthorizationGroup__Form:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input[name = "login"]').val(params.account.login);
        // form.find('input[name = "password"]').val(params.account.password);

        // reactjs
        provider.eval(
            "var FindReact = function (dom) {" +
            "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
            "        return dom[key];" +
            "    }" +
            "    return null;" +
            "};" +
            "FindReact(document.querySelector('input[name = \"login\"]')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});" +
            "FindReact(document.querySelector('input[name = \"password\"]')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
        );

        provider.setNextStep('checkLoginErrors');
        let login = form.find('button[type = "submit"]');
        login.click();

        setTimeout(function () {
            plugin.checkLoginErrors(params);
        }, 7000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        let errors = $('div.DS__Banner__Banner_icon_error:visible, div.DS__Tooltip__invalid:visible > p, div.DS__Textfield__Tooltip_invalid:visible > p, div.DS__Banner__icon_error:visible div.DS__Text__noIndent, div.DS__StatusMessage__view_error:visible div.DS__Text__root, div.DS__StatusMessage__view_error:visible div.DS__Text__root, div.DS__FieldTooltip__invalid:visible').text();

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');

        if ('boolean' == typeof params.account.itineraryAutologin
            && params.account.itineraryAutologin
            && params.account.accountId > 0
        ) {
            return provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.s7.ru/?_=' + new Date().getTime();
            });
        }

        provider.complete();
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        var waitCounter = 0;
        var waitPageLoad = setInterval(function () {
            if ($('h2:contains("Booking details")')) {
                clearInterval(waitPageLoad);
                plugin.itLoginComplete(params);
            }
            if (++waitCounter > 60 || $('h2:contains("Service error")')) {
                clearInterval(waitPageLoad);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        $('div.mainpage-bot li:eq(2)').click();

        let form = $('div#bot-avia-js form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        let surName = '', confNo = '';
        if ('undefined' !== typeof params.account.properties.confFields) {
            surName = params.account.properties.confFields.LastName;
            confNo = params.account.properties.confFields.ConfNo;
        } else if (!surName && 'undefined' != typeof params.account.properties.Name) {
            surName = params.account.properties.Name.split(' ')[1];
            if ('undefined' != typeof params.account.properties.confirmationNumber)
                confNo = params.account.properties.confirmationNumber;
        }

        // $('input:eq(0)', form).val(surName);
        // $('input:eq(1)', form).val(confNo);
        // reactjs
        provider.eval(
            "var FindReact = function (dom) {" +
            "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
            "        return dom[key];" +
            "    }" +
            "    return null;" +
            "};" +
            "FindReact(document.querySelectorAll('div#bot-avia-js form input')[0]).onChange({target:{value:'" + surName + "'}, preventDefault:function(){}});" +
            "FindReact(document.querySelectorAll('div#bot-avia-js form input')[1]).onChange({target:{value:'" + confNo + "'}, preventDefault:function(){}});"
        );

        return provider.setNextStep('toItineraries', function () {
            $('button[type="submit"]', form).click();
            setTimeout(function () {
                plugin.toItineraries(params);
            }, 7000)
        });
    }

};
