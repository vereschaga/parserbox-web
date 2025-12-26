var plugin = {
    flightStatus:{
        url: 'https://www.virginamerica.com/check-flight-status',
        match: /^(?:VX)?\d+/i,

        start: function () {
            browserAPI.log("start");
            // click "Know the flight number?"
            api.eval("$('a[ng-click *= \"flightNumber\"]').click();");
            var form = $("form.flight-num-form");
            if (form.length == 1) {
                var flightNumber = params.flightNumber.replace(/VX/gi, '');

                var today     = new Date();
                var yesterday = new Date();
                var tomorrow  = new Date();

                yesterday.setDate(yesterday.getDate() - 1);
                tomorrow.setDate(tomorrow.getDate() + 1);
                var depDate = api.getDepDate();

                var depDateValue = '';
                if (depDate.getDate() == yesterday.getDate())
                    depDateValue = 'Yesterday';
                else if(depDate.getDate() == today.getDate())
                    depDateValue = 'Today';
                else if(depDate.getDate() == tomorrow.getDate())
                    depDateValue = 'Tomorrow';
                depDateValue = $('select[name = dateAlt]').find('option:contains(' + depDateValue + ')').val();
                if (depDateValue != '') {
                    // angularjs
                    api.eval('var scope = angular.element("input[name = \'flightNum\']").scope();' +
                        'scope.input.validateValue("' + flightNumber + '");' +
                        'scope.input.value = "' + flightNumber + '";' +
                        'scope.input.isValid = true;' +
                        'var scope = angular.element("select[name = \'dateAlt\']").scope();' +
                        'scope.select.validateValue("' + depDateValue + '");' +
                        'scope.select.value = "' + depDateValue + '";' +
                        '$("select[name = dateAlt]").val("' + depDateValue + '");' +
                        '$("select[name = dateAlt]").find("option[value =' + depDateValue + ']").change();'
                    );

                    api.setNextStep('finish', function () {
                        // angularjs
                        api.eval('var scope = angular.element("form.flight-num-form").scope();' +
                            'scope.$apply(function(){' +
                            'scope.formHandler.fields.dateAlt.value = scope.formHandler.fields.dateAlt.options[' + depDateValue + '];' +
                            'scope.formHandler.fields.flightNum.value = "'+ flightNumber +'";' +
                            'scope.flightStatus.checkStatus();});'
                        );
                    });
                }else{
                    api.errorDate();
                }
            }
        },

        finish: function () {
            if($('.un_bold:contains("Departing")').length > 0){
                api.complete();
            }else{
                api.error($('.un_error').eq(1).text());
            }

        }
    },
    // Old
    autologin: {

        url: "http://m.virginamerica.com/mt/www.virginamerica.com?un_jtt_v_signin=true",

        start: function(){
            if (this.isLoggedIn())
                this.toDetailsPage('loggedIn');
            else
                this.login();
        },

        loggedIn: function(){
            if (this.isSameAccount())
                this.finish();
            else
                this.logout();
        },

        login: function () {
            var submitButton = $('input[value*="Sign In"]').eq(1);
            if (submitButton.length == 1) {
                $('input[name="unLoginId"]').val(params.login);
                $('input[name="unPassword"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function(){
                    submitButton.click();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.properties) != 'undefined' &&
                typeof(params.properties.Number) != 'undefined' &&
                params.properties.Number != '' &&
                $('#memberInfo3:contains("'+ params.properties.Number +'")').length > 0);
        },

        isLoggedIn: function () {
            if($('form[name="unSignInForm"]').length > 0){
                return false;
            }
            if($('input[name="un_jtt_signout"]').length > 0){
                return true;
            }

            api.error("can't determine login state");
            return false;
        },

        checkLoginErrors: function () {
            var error = $('#loginFailed');

            if (error.length > 0) {
                api.error(error.text());
            } else {
                this.toDetailsPage('finish');
            }
        },

        logout: function () {
            api.setNextStep('toLoginPage', function () {
                $('input[name="un_jtt_signout"]').get(0).click();
            });
        },

        toLoginPage: function(){
            var loginUrl = this.url;
            api.setNextStep('login', function(){
                document.location.href = loginUrl;
            })
        },

        toDetailsPage: function(nextStep){
            api.setNextStep(nextStep, function(){
                document.location.href = $('a[href*="homeProfileWithPagination"]').attr('href');
            });
        },

        finish: function () {
            api.complete();
        }
    }
};