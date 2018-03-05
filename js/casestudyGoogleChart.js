// Load the Visualization API and the chart package.
google.load("visualization", "1", {
    packages:["corechart", "gauge", "orgchart", "geochart"]
});

(function ($) {
    Drupal.behaviors.casestudy = {
        attach: function (context, settings) {
            //console.log(settings);
            if($('#gchart').length > 0){
                google.setOnLoadCallback(drawChart);
            }
            function drawChart() {
                var dataTable = new google.visualization.DataTable();
              
                dataTable.addColumn('string', 'Label');
                dataTable.addColumn({
                    type: 'string', 
                    role: 'tooltip'
                });
                dataTable.addColumn('number', 'Question');
                dataTable.addColumn('number', 'Question');
                dataTable.addColumn('number', 'Question');
                dataTable.addColumn('number', 'Question');
                // A column for custom tooltip content
                
                var row = new Array();
                for (var j in settings.chart.rows) {
                    row[j] = settings.chart.rows[j];
                    
                } 
               dataTable.addRows(row)
          
               /*
               dataTable.addRows([
                    ['$600K in our first year!' , '2010', 0.2, 0,0,0],
                    ['$600K in our first year!' , '2011', 0,  0.3, 0,0 ],
                    ['$600K in our first year!' , '2012', 0, 0, 0.5,0],
                    ['$600K in our first year!' , '2012', 0, 0, 0, 1],
                    ]);
                 */   
                var options = {
                    tooltip: {
                        isHtml: true
                    },
                    legend: 'none',
                    focusTarget: 'category',
                    isStacked: true,
                    bar: {
                        groupWidth: 20
                    },
                    hAxis: {
                        format: '###%', 
                        gridlines: {
                            count: 9
                        }
                    },
                    colors: settings.chart.colors
                };
                var chart = new google.visualization.BarChart(document.getElementById('gchart'));
                chart.draw(dataTable, options);
            }
        }
    };

}(jQuery));
