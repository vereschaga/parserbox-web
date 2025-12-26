var plugin = {

	hosts:{'ya.ru': true},

	getStartingUrl:function (params) {
		return "https://ya.ru";
	},

    start: function (params) {
        browserAPI.log("start");

        if (params.account.mode === 'confirmation') {
            plugin.parseByConfNo(params);
            return;
        }// if (params.account.mode == 'confirmation')

        // params.account.historyStartDate will be 0 or unix timestamp, rounded to midnight
        // you should parse all history with dates <= params.account.historyStartDate (UTC)

        var historyOnSite = [
            {
                'Post Date': 1445904000,
                'Type': 'Bonus',
                'Eligible Nights': 0,
                'Bonus': '+500',
                'Description': 'PURCHASED POINTS-MEMBER SELF'
            },
            {
                'Post Date': 1439424000,
                'Type': 'Award',
                'Eligible Nights': '-',
                'Starpoints': '-2,500',
                'Description': 'SINGAPORE AIRLINES KRISFLYER'
            }
        ];

        var history = [];
        $.each(historyOnSite, function(index, row){
            if(row['Post Date'] >= params.account.historyStartDate)
                history.push(row);
        });

        provider.saveProperties({
            Balance: 100,
            // GetHistoryColumns implemented in function.php, do not return it here
            HistoryRows: history
        });

        provider.complete();
	},

    parseByConfNo: function (params) {
        var itinerary = {};
        itinerary.RecordLocator = 'RECLOC1';
        itinerary.TripSegments = [];

        var segment = {};
        segment.FlightNumber = '3223';
        segment.AirlineName = 'TestExt Airlines';
        segment.DepCode = 'JFK';
        segment.ArrCode = 'LAX';
        d = Math.round(Math.round((new Date()) / 1000) / 60) * 60 + 86400 + 3600;
        segment.DepDate = d;
        segment.ArrDate = d + 7200;
        segment.Duration = '2h 10m';

        itinerary.TripSegments.push(segment);

        var properties = {};
        properties.Itineraries = [itinerary];
        provider.saveProperties(properties);

        provider.complete();
    }

}
