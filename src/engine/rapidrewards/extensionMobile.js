var plugin = {

    flightStatus:{
        url: 'https://mobile.southwest.com/p?stpp=true&formid=main#_main',
        match: /^(?:WN)?\s*\d+/i,
        reload: true,

        start: function () {
            var counter = 0;
            var iframeInterval = setInterval(function(){
                var link = $('div[id *= "FlightStatus"]');
                console.log('searching link');
                if (link.length > 0) {
//                    link.click(function(e){kony.widgets.Segment.segmentEventHandler(e);}); // android workaround
                    clearInterval(iframeInterval);
//                    link.click();
                    link.trigger('click');

                    plugin.flightStatus.clickMenuLink();
                }
                if (counter > 20) {
                    clearInterval(iframeInterval);
                    api.error('timeout: iframe not found');
                }
                counter++;
            }, 500);
        },

        clickMenuLink: function(){
            console.log('clickMenuLink');
            api.setNextStep(''); // stop injecting - we use setInterval!
            var counter = 0;
            var formSearch = setInterval(function () {
                var depSelect = $('#frmCheckFlightStatus_btnFrom');
                var arrSelect = $('#frmCheckFlightStatus_btnTo');
                var input = $('#frmCheckFlightStatus_txtFlightNumber');
                var searchButton = $('#frmCheckFlightStatus_btnSearch');
                if (depSelect.length == 1 && arrSelect.length == 1 && input.length == 1 && searchButton.length == 1) {
                    console.log('searching...');
                    clearInterval(formSearch);
                    counter = 0;
                    // from
                    depSelect.trigger('click');
                    var select = $('div#segCityListbox_lblcityName');
                    var depOptionVal = select.find('label:contains("' + params.depCode + '")').text();
                    console.log('depOptionVal ' +  depOptionVal );
                    select.find('label:contains("' + params.depCode + '")').trigger('click');
                }
                if (counter > 10) {
                    clearInterval(formSearch);
                    api.error('unable to find search form');
                }
                counter++;
            }, 500);

            counter = 0;
            var formSearch2 = setInterval(function () {
                var depSelect = $('#frmCheckFlightStatus_btnFrom');
                var arrSelect = $('#frmCheckFlightStatus_btnTo');
                var input = $('#frmCheckFlightStatus_txtFlightNumber');
                var searchButton = $('#frmCheckFlightStatus_btnSearch');
                if (depSelect.length == 1 && arrSelect.length == 1 && input.length == 1 && searchButton.length == 1) {
                    console.log('searching...');
                    clearInterval(formSearch2);
                    counter = 0;
                    // to
                    arrSelect.trigger('click');
                    var select = $('div#segCityListbox_lblcityName');
                    var arrOptionVal = select.find('label:contains("' + params.arrCode + '")').text();
                    select.find('label:contains("' + params.arrCode + '")').trigger('click');
                    console.log('arrOptionVal ' +  arrOptionVal );

                    arrSelect.val(arrOptionVal);
                    console.log('depSelect ' +  depSelect.val() );
                    console.log('arrSelect ' +  arrSelect.val() );
                }
                if (counter > 10) {
                    clearInterval(formSearch2);
                    api.error('unable to find search form');
                }
                counter++;
            }, 500);

            var formSearch3 = setInterval(function () {
                var input = $('#frmCheckFlightStatus_txtFlightNumber');
                var searchButton = $('#frmCheckFlightStatus_btnSearch');
                if (searchButton.length == 1 && input.length == 1) {
                    console.log('number...');
                    clearInterval(formSearch3);
                    input.val(params.flightNumber.replace(/WN/gi, ''));
                    console.log('flightNumber ' +  input.val() );
                    console.log('date: ' + $.format.date(api.getDepDate(), 'MM/dd/yyyy'));
                    // global vars
                    gFSTavelDate = $.format.date(api.getDepDate(), 'MM/dd/yyyy');
                    gFlightNumber = params.flightNumber.replace(/WN\s*/gi, '');

//                    if (depDateElem.length == 1) {
                        setTimeout(function(){searchButton.trigger('click')}, 0);
//                    }else{
//                        api.errorDate();
//                    }
                }
                if (counter > 10) {
                    clearInterval(formSearch3);
                    api.error('unable to find search form');
                }
                counter++;
            }, 500);

            counter = 0;
            var detailsSearch = setInterval(function(){
                console.log('detailsSearch');
                var details = $('div label:contains(' + params.flightNumber.replace(/WN/gi, '') + ')');
                var input = $('#frmCheckFlightStatus_txtFlightNumber');
                if (details.length > 0) {
                    console.log('search');
                    details.trigger('click');
                    clearInterval(detailsSearch);
                    clearInterval(formSearch);
                    api.complete();
                }
                if (counter > 10 && input.length > 0) {
                    clearInterval(detailsSearch);
                    api.error('Sorry. We are unable to process your request at this time. Please contact Southwest Airlines for assistance.');
                }
                counter++;
            }, 500);
        }
    },

    autologin: {

        getStartingUrl : function(params) {
            return 'https://mobile.southwest.com/my-account';
        },

        start: function () {
            browserAPI.log("start");
            setTimeout(function() {
                if (plugin.autologin.isLoggedIn()) {
                    if (plugin.autologin.isSameAccount())
                        plugin.autologin.finish();
                    else
                        plugin.autologin.logout();
                } else
                    plugin.autologin.login();
            }, 3000);
        },

        isLoggedIn : function() {
            browserAPI.log("isLoggedIn");
            if ($('div.login-btn:contains("Log out"),label:contains("Rapid Rewards #")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[name="login"],span.login-button__box:contains("Log in")').length) {
                browserAPI.log('not logged in');
                return false;
            }
            api.setError(util.errorMessages.unknownLoginState);
        },

        isSameAccount : function() {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) != 'undefined' &&
                ((typeof(params.properties.Number) != 'undefined' &&
                params.properties.Number != '' &&
                $('span:contains("' + params.properties.Number + '")').length > 0)
                || (typeof (params.properties.Name) != 'undefined'
                && $('span:contains("' + params.properties.Name.split(' ')[0] + '")').length)))
        },

        login : function() {
            browserAPI.log("login");
            $('span.login-button--box:contains("Log in")').click();
            var counter = 0;
            var login   = setInterval(function() {
                var form = $('form[name="login"]');
                browserAPI.log("waiting... " + counter);
                if (form.length > 0) {
                    clearInterval(login);
                    browserAPI.log("submitting saved credentials");
                    // form.find('input[name = "userNameOrAccountNumber"]').val(params.account.login);
                    // // truncating password to 16 chars
                    // util.setInputValue(form.find('input[name = "password"]'), params.account.password.substring(0, 16));

                    // reactjs
                    provider.eval(
                        "function triggerInput(selector, enteredValue) {\n" +
                        "      let input = document.querySelector(selector);\n" +
                        "      input.dispatchEvent(new Event('focus'));\n" +
                        "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                        "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                        "      nativeInputValueSetter.call(input, enteredValue);\n" +
                        "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                        "      input.dispatchEvent(inputEvent);\n" +
                        "}\n" +
                        "triggerInput('input[name = \"userNameOrAccountNumber\"]', '" + params.account.login + "');\n" +
                        "triggerInput('input[name = \"password\"]', '" + params.account.password.substring(0, 16) + "');"
                    );

                    api.setNextStep('checkLoginError', function() {
                        form.find('#login-btn').click();
                        setTimeout(function(){
                            plugin.autologin.checkLoginError();
                        }, 5000);
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    api.setError(util.errorMessages.loginFormNotFound);
                }
                counter++;
            }, 500);
        },

        logout : function() {
            browserAPI.log("logout");
            api.setNextStep('start', function() {
                $('div.login-btn:contains("Log out")').get(0).click();
                setTimeout(function(){
                    plugin.autologin.login();
                }, 3000);
            });
        },

        checkLoginError: function () {
            browserAPI.log("checkLoginError");
            const $error = $('div[data-qa="global-error-popup"] h3.popup-title:visible, div.error-header:visible');

            if ($error.length && util.trim($error.text()) !== "") {
                api.setError(util.trim($error.text()));
                return;
            }

            this.finish();
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};