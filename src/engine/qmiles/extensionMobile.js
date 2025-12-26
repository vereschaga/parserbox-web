
var plugin = {
    flightStatus: {
    	url: 'https://m.qatarairways.com/qrmobile/flightStatus/flightSearch.htm',
        match: /^(?:QR)?\d+/i,
        reload: true,

    	start: function () {
            var input = $('#flightNumber');
           	var button = $('#check-status');
           	if (input.length == 1 && button.length == 1){
                $('li > h1:contains("Flight Number")').click();
                $('#searchType').val('number');
           		input.val(params.flightNumber.replace(/QR/gi, ''));

                $('#searchDate').val($.format.date(api.getDepDate(), 'dd MMM yyyy'));
                api.setNextStep('finish', function(){
           			button.click();
           		});
           	}
    	},

    	finish: function () {
            if ($('.flight-no-detail-section').length > 0) {
                api.complete();
            } else {
                api.error($('#error-message').text().trim());
            }
    	}
    },

    autologin: {
        url: "https://m.qatarairways.com/mobile/profile/user/socialLogin.htm?selLang=en&requestType=LOGIN&lang=en&deeplinkredirected=true",
        clearCache: true,

        loadStart: function () {
            browserAPI.log("start");
            provider.setNextStep('start', function(){
                document.location.href = plugin.autologin.url;
            });
        },

        start: function () {
            browserAPI.log("start");
            util.waitFor({
                selector: 'div#profileLogin',
                success: function(){
                    plugin.autologin.start2();
                },
                fail: function(){
                    provider.setError(util.errorMessages.unknownLoginState);
                }
            });
        },

        start2: function () {
            browserAPI.log("start2");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            } else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('div#signOut').length > 0) {
                browserAPI.log("isLoggedIn = true");
                return true;
            }
            if ($('input#ffpNumber').length > 0) {
                browserAPI.log('isLoggedIn = false');
                return false;
            }
            provider.setError(util.errorMessages.unknownLoginState);
        },

        login: function () {
            browserAPI.log("login");
            util.waitFor({
                selector: 'div#profileLogin',
                success: function(){
                    browserAPI.log("submitting saved credentials");
                    $('input#ffpNumber').val(params.login);
                    $('input#password').val(params.pass);
                    $('div#profileLogin').click();
                    plugin.autologin.verify();
                },
                fail: function(){
                    provider.setError(util.errorMessages.unknownLoginState);
                },
                timeout: 10
            });
        },

        verify: function () {
            browserAPI.log("login");
            util.waitFor({
                selector: 'div#generateOtp',
                success: function(elem){
                    provider.setError('Security verification');
                    // elem.click();
                    // plugin.autologin.checkLoginErrors();
                },
                fail: function(){
                }
            });
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return false;
            // return (typeof(params.properties) !== 'undefined')
            //    && (typeof(params.properties.Number) !== 'undefined')
            //    && ($('span:contains("' + params.properties.Number + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            util.waitFor({
                selector: 'span.errorMsg',
                success: function(elem){
                    provider.setError(elem.text());
                },
                fail: function(){
                    plugin.autologin.finish();
                },
                timeout: 3
            });
        },

        logout: function () {
            browserAPI.log("logout");
            provider.setNextStep('loadStart', function () {
                document.location.href = 'https://m.qatarairways.com/mobile/profile/signOut.htm';
            });
        },

        finish: function () {
            browserAPI.log("finish");
            provider.complete();
        }
    },

    waitFor: function(structure) {
        // timeout in seconds
        var timeout = structure.timeout;
        if (!timeout)
            timeout = 5;
        var counter = 0;
        var wait = setInterval(function(){
            browserAPI.log('waiting.. ' + counter + '/' + timeout);
            var elem = $(structure.selector);
            if (elem.length > 0) {
                clearInterval(wait);
                structure.success(elem);
            }
            if (counter >= timeout) {
                clearInterval(wait);
                structure.fail();
            }
            counter += 1;
        }, 1000);
    }


};
