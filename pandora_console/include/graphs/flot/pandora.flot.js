/*


*/

function pandoraFlotPie(graph_id, values, labels, nseries, width, font_size, water_mark, separator, legend_position, height, colors, hide_labels) {
	var labels = labels.split(separator);
	var data = values.split(separator);
	if (colors != '') {
		colors = colors.split(separator);
	}
	
	var pieRadius = 0.9;

	var color = null;
	for (var i = 0; i < nseries; i++) {
		if (colors != '') {
			color = colors[i];
		}
		
		data[i] = { label: labels[i], data: parseFloat(data[i]), color: color}
	}

	var label_conf;

	if (width < 400 || hide_labels) {
		label_conf = {
			show: false
		};
	}
	else {
		label_conf = {
			show: true,
			radius: pieRadius,
			formatter: function(label, series) {
				return '<div style="font-size:' + font_size + 'pt;' +
					'text-align:center;padding:2px;color:white;">' +
						label + '<br/>' + series.percent.toFixed(2) + '%</div>';
			},
			background: {
				opacity: 0.5,
				color: '#000'
			}
		};
	}

	var show_legend = true;
	if (legend_position == 'hidden') {
		show_legend = false;
	}

	var conf_pie = {
			series: {
				pie: {
					show: true,
					radius: pieRadius,
					//offset: {top: -100},
					label: label_conf,
					//$label_str
				}
			},
			legend: {
				show: show_legend
			},
			grid: {
				hoverable: true,
				clickable: true
			}
		};

		if (width < 400) {
			conf_pie.legend.labelFormatter = function(label, series) {
					return label + " (" + series.percent.toFixed(2) + "%)";
				}
		}

	switch (legend_position) {
		case 'bottom':
			conf_pie.legend.position = "se";
			break;
		case 'right':
		case 'inner':
			conf_pie.legend.container = $('#'+graph_id+"_legend");
		default:
			//TODO FOR TOP OR LEFT OR RIGHT
			break;
	}

	var plot = $.plot($('#'+graph_id), data, conf_pie);

	var legends = $('#'+graph_id+' .legendLabel');
	legends.css('font-size', font_size+'pt');

	// Events
	$('#' + graph_id).bind('plothover', pieHover);
	$('#' + graph_id).bind('plotclick', pieClick);
	$('#' + graph_id).bind('mouseout',resetInteractivity);
	$('#' + graph_id).css('margin-left', 'auto');
	$('#' + graph_id).css('margin-right', 'auto');

	function pieHover(event, pos, obj) {
		if (!obj) return;

		index = obj.seriesIndex;
		legends.css('color', '#3F3F3D');
		legends.eq(index).css('color', '#000000');
	}

	// Reset styles
	function resetInteractivity() {
		legends.css('color', '#3F3F3D');
	}
	
	if (water_mark) {
		set_watermark(graph_id, plot,
			$('#watermark_image_' + graph_id).attr('src'));
	}
}

function pandoraFlotPieCustom(graph_id, values, labels, width,
			font_size, font, water_mark, separator, legend_position, height,
				colors,legend) {
	font = font.split("/").pop().split(".").shift();
	var labels = labels.split(separator);
	var legend = legend.split(separator);
	var data = values.split(separator);
	var no_data = 0;
	if (colors != '') {
		colors = colors.split(separator);
	}
	var colors_data = ['#FC4444','#FFA631','#FAD403','#5BB6E5','#F2919D','#80BA27'];
	var color = null;
	for (var i = 0; i < data.length; i++) {
		if (colors != '') {
			color = colors_data[i];
		}
		var datos = data[i];
		data[i] = { label: labels[i], data: parseFloat(data[i]), color: color };
		if (!datos)
			no_data++;
		
	}
	
	var label_conf;
	
	label_conf = {
		show: true,
		radius: 0.75,
		formatter: function(label, series) {
			return '<div style="font-size:' + font_size + 'pt;' +
				'text-align:center;padding:2px;color:white;">' +
				series.percent.toFixed(2) + '%</div>';
		},
		background: {
			opacity: 0.5,
			color: '#000'
		}
	};
	
	var show_legend = true;
	
	var conf_pie = {
			series: {
				pie: {
					show: true,
					radius: 3/4,
					innerRadius: 0.4,
					label: label_conf
					
				}
			},
			legend: {
				show: show_legend,
			},
			grid: {
				hoverable: true,
				clickable: true
			}
		};
	
	var plot = $.plot($('#'+graph_id), data, conf_pie);
	if (no_data == data.length) {
		$('#'+graph_id+' .overlay').remove();
		$('#'+graph_id+' .base').remove();
		$('#'+graph_id).prepend("<img style='width:50%;' src='images/no_data_toshow.png' />");
		
	}
	var legends = $('#'+graph_id+' .legendLabel');
	var j = 0;
	legends.each(function () {
		//$(this).css('width', $(this).width());
		$(this).css('font-size', font_size+'pt');
		$(this).removeClass("legendLabel");
		$(this).addClass(font);
		$(this).text(legend[j]);
		j++;
	});
	
	if ($('input[name="custom_graph"]').val()) {
		$('.legend>div').css('right',($('.legend>div').height()*-1));
		$('.legend>table').css('right',($('.legend>div').height()*-1));
	}
	$('.legend>table').css('border',"1px solid #E2E2E2");
	$('.legend>table').css('background-color',"#FFF");
	
	
	var pielegends = $('#'+graph_id+' .pieLabelBackground');
	pielegends.each(function () {
		$(this).css('transform', "rotate(-35deg)");
	});
	var labelpielegends = $('#'+graph_id+' .pieLabel');
	labelpielegends.each(function () {
		$(this).css('transform', "rotate(-35deg)");
	});
	
	// Events
	$('#' + graph_id).bind('plothover', pieHover);
	$('#' + graph_id).bind('plotclick', Clickpie);
	$('#' + graph_id).bind('mouseout',resetInteractivity);
	$('#' + graph_id).css('margin-left', 'auto');
	$('#' + graph_id).css('margin-right', 'auto');
	
	function pieHover(event, pos, obj) {
		if (!obj) return;
		
		index = obj.seriesIndex;
		legends.css('color', '#3F3F3D');
		legends.eq(index).css('color', '#000000');
	}
	
	function Clickpie(event, pos, obj) {
		if (!obj) return;
		percent = parseFloat(obj.series.percent).toFixed(2);
		valor = parseFloat(obj.series.data[0][1]);
		
		if (valor > 1000000){
			value = Math.round((valor / 1000000)*100)/100;
			value = value + "M";
		}else{ if (valor > 1000) {
				value = Math.round((valor / 1000)*100)/100;
				value = value + "K";
			}
			else
				value = valor;
		}
		
		alert(''+obj.series.label+': '+ value +' ('+percent+'%)');
	}
	
	// Reset styles
	function resetInteractivity() {
		legends.each(function () {
			// fix the widths so they don't jump around
			$(this).css('color', '#3F3F3D');
		});
	}
	
	if (water_mark) {
		set_watermark(graph_id, plot,
			$('#watermark_image_' + graph_id).attr('src'));
	}
}

function pandoraFlotHBars(graph_id, values, labels, water_mark,
	maxvalue, water_mark, separator, separator2, font, font_size) {

	var colors_data = ['#FC4444','#FFA631','#FAD403','#5BB6E5','#F2919D','#80BA27'];
	values = values.split(separator2);
	font = font.split("/").pop().split(".").shift();
	var datas = new Array();
	for (i = 0; i < values.length; i++) {
		var serie = values[i].split(separator);


		var aux = new Array();
		for (j = 0; j < serie.length; j++) {
			var aux2 = parseFloat(serie[j]);
			aux.push([aux2, j]);
			datas.push( {
				data: [[aux2, j]],
				color: colors_data[j]
			});
		};
	}



	var labels_total=new Array();
	labels = labels.split(separator);
	i = 0;
	for (i = 0; i < labels.length; i++) {
		labels_total.push([i, labels[i]]);
	}

	var stack = 0, bars = true, lines = false, steps = false;
	var k=0;
	var options = {
			series: {
				bars: {
					show: true,
					barWidth: 0.75,
					align: "center",
					lineWidth: 1,
					fill: 1,
					horizontal: true,
				}
			},
			grid: {
				hoverable: true,
				borderWidth: 1,
				backgroundColor: { colors: ["#FFF", "#FFF"] }
				},
			yaxis:  {
					axisLabelUseCanvas: true,
					axisLabelFontSizePixels: 12,
					axisLabelFontFamily: font+'Font',
					axisLabelPadding: 3,
					ticks: yFormatter,
					tickSize: 1,
					color: '#000',
					},
			legend: {
				show: false
			}
		};

	// Fixed to avoid the graphs with all 0 datas
	// the X axis show negative part instead to
	// show the axis only the positive part.
	if (maxvalue == 0) {
		options['yaxis']['min'] = 0;
		// Fixed the values with a lot of decimals in the situation
		// with all 0 values.
		options['yaxis']['tickDecimals'] = 0;
	}


	var plot = $.plot($('#' + graph_id), datas, options );
	
	$('#' + graph_id).HUseTooltip();
	$('#' + graph_id).css("margin-left","auto");
	$('#' + graph_id).css("margin-right","auto");
	$('#' + graph_id).find('div.legend-tooltip').tooltip({ track: true });
	// Adjust the top of yaxis tick to set it in the middle of the bars
	//yAxisHeight = $('#' + graph_id + ' .yAxis .tickLabel')
		//.css('height').split('px')[0];
	
	//~ i = 0;
	//~ $('#' + graph_id + ' .yAxis .tickLabel').each(function() {
		//~ $(this).css('display','none');
		//~ $(this).addClass("legend_"+i);
		//~ i++;
	//~ });
	
	$('#' + graph_id + ' .xAxis .tickLabel').each(function() {
		/*
		tickTop = $(this).css('top').split('px')[0];
		tickNewTop = parseInt(parseInt(tickTop) - (yAxisHeight / 2) - 3);
		$(this).css('top', tickNewTop + 'px');

		valuesNewTop = parseInt(parseInt(tickTop) - (yAxisHeight));

		$('#value_' + i + '_' + graph_id)
			.css('top',parseInt(plot.offset().top) + parseInt(valuesNewTop));

		pixelPerValue = parseInt(plot.width()) / maxvalue;

		inCanvasValuePos = parseInt(pixelPerValue *
			($('#value_' + i + '_' + graph_id).html()));
		label_width = ($('#value_' + i + '_' + graph_id)
			.css('width').split('px')[0] - 3);

		label_left_offset = plot.offset().left + inCanvasValuePos + 5; //Label located on right side of bar + 5 pixels

		//If label fit into the bar just recalculate left position to fit on right side of bar
		if (inCanvasValuePos > label_width) {
			label_left_offset = plot.offset().left + inCanvasValuePos
				- $('#value_' + i + '_' + graph_id).css('width').split('px')[0] - 3;
		}

		$('#value_' + i + '_' + graph_id)
			.css('left', label_left_offset);
		i++;
		*/
		label = parseFloat($(this).text());
		text = label.toLocaleString();
		if ( label >= 1000000)
			text = text.substring(0,4) + "M";
		else if (label >= 100000)
				text = text.substring(0,3) + "K";
			else if (label >= 1000)
					text = text.substring(0,2) + "K";
		
		$(this).text(text);
		
	});
	/*
	// When resize the window, adjust the values
	$('#' + graph_id).parent().resize(function () {
		i = 0;
		pixelPerValue = parseInt(plot.width()) / maxvalue;

		$('#' + graph_id + ' .yAxis .tickLabel').each(function() {
			inCanvasValuePos = parseInt(pixelPerValue *
				($('#value_' + i + '_' + graph_id).html()));
			label_width = ($('#value_' + i + '_' + graph_id)
				.css('width').split('px')[0] - 3);

			label_left_offset = plot.offset().left + inCanvasValuePos + 5; //Label located on right side of bar + 5 pixels

			//If label fit into the bar just recalculate left position to fit on right side of bar
			if (inCanvasValuePos > label_width) {
				label_left_offset = plot.offset().left + inCanvasValuePos
					- $('#value_' + i + '_' + graph_id)
						.css('width').split('px')[0] - 3;
			}

			$('#value_' + i + '_' + graph_id)
				.css('left', label_left_offset);
			i++;
		});
	});

	// Format functions
	function xFormatter(v, axis) {
		if (labels[v] != undefined) {
			return labels[v];
		}
		else {
			return '';
		}
	}

	function yFormatter(v, axis) {
		return v;
	}

	// Events
	$('#' + graph_id).bind('plothover',  function (event, pos, item) {
		$('.values_' + graph_id).css('font-weight', '');
		if (item != null) {
			index = item.dataIndex;
			$('#value_' + index + '_' + graph_id)
				.css('font-weight', 'bold');
		}
	});

	if (water_mark) {
		set_watermark(graph_id, plot,
			$('#watermark_image_' + graph_id).attr('src'));
	}

	if (maxvalue == 0) {

		// Fixed the position for the graphs with all values in
		// bars is 0.

		$(".values_" + graph_id).css("left", (plot.offset().left + 5) + "px");
	}
	* */
	function yFormatter(v, axis) {
		format = new Array();
		for (i = 0; i < labels_total.length; i++) {
			var label = labels_total[i][1];
			var shortLabel = reduceText(label, 30);
			var title = '';
			if (label !== shortLabel) {
				title = label;
				label = shortLabel;
			}
			format.push([i,'<div style=font-size:'+font_size+'pt title="'+title+'" class="'+font+'">'
				+ label
				+ '</div>']);
		}
		return format;
	}
	
	if (water_mark) {
		set_watermark(graph_id, plot, $('#watermark_image_'+graph_id).attr('src'));
	}
}

var previousPoint = null, previousLabel = null;

$.fn.HUseTooltip = function () {
    $(this).bind("plothover", function (event, pos, item) {
        if (item) {
            if ((previousLabel != item.series.label) || (previousPoint != item.seriesIndex)) {
                previousPoint = item.seriesIndex;
                previousLabel = item.series.label;
                $("#tooltip").remove();

                var x = item.datapoint[0];
                var y = item.datapoint[1];

                var color = item.series.color;              
                showTooltip(pos.pageX,
                        pos.pageY,
                        color,
                        "<strong>" + x + "</strong>");
            }
        } else {
            $("#tooltip").remove();
            previousPoint = null;
        }
    });
};
$.fn.VUseTooltip = function () {
    $(this).bind("plothover", function (event, pos, item) {
        if (item) {
            if ((previousLabel != item.series.label) || (previousPoint != item.seriesIndex)) {
                previousPoint = item.seriesIndex;
                previousLabel = item.series.label;
                
                $("#tooltip").remove();

                var x = item.datapoint[0];
                var y = item.datapoint[1];
				
                var color = item.series.color;
                showTooltip(pos.pageX,
                        pos.pageY,
                        color,
                        "<strong>" + y + "</strong>");
            }
        } else {
            $("#tooltip").remove();
            previousPoint = null;
        }
    });
};

function showTooltip(x, y, color, contents) {
    $('<div id="tooltip">' + contents + '</div>').css({
        position: 'absolute',
        display: 'none',
        top: y,
        left: x,
        border: '2px solid ' + color,
        padding: '3px',
        'font-size': '9px',
        'border-radius': '5px',
        'background-color': '#fff',
        'font-family': 'Verdana, Arial, Helvetica, Tahoma, sans-serif',
        opacity: 0.9
    }).appendTo("body").fadeIn(200);
}

function pandoraFlotVBars(graph_id, values, labels, labels_long, legend, colors, water_mark, maxvalue, water_mark, separator, separator2, font, font_size ) {

	values = values.split(separator2);
	legend = legend.split(separator);
	font = font.split("/").pop().split(".").shift();
	labels_long = labels_long.length > 0 ? labels_long.split(separator) : 0;
	colors = colors.length > 0 ? colors.split(separator) : [];
	var colors_data = colors.length > 0
		? colors
		: ['#FC4444','#FFA631','#FAD403','#5BB6E5','#F2919D','#80BA27'];
	var datas = new Array();
	
	for (i = 0; i < values.length; i++) {
		var serie = values[i].split(separator);
		
		
		var aux = new Array();
		for (j = 0; j < serie.length; j++) {
			var aux2 = parseFloat(serie[j]);
			aux.push([aux2, j]);
			datas.push( {
				data: [[j, aux2]],
				color: colors_data[j]
			});
		};
	}
	
	var labels_total=new Array();
	labels = labels.split(separator);
	i = 0;
	for (i = 0; i < labels.length; i++) {
		labels_total.push([i, labels[i]]);
	}
	
	var stack = 0, bars = true, lines = false, steps = false;
	
	var options = {
		series: {
			bars: {
				show: true,
				lineWidth: 1,
				fill: 1,
				align: "center",
				barWidth: 1
			}
		},
		xaxis: {
			axisLabelUseCanvas: true,
			axisLabelFontSizePixels: 7,
			axisLabelFontFamily: font+'Font',
			axisLabelPadding: 0,
			ticks: xFormatter,
			labelWidth: 130,
		},
		yaxis: {
			axisLabelUseCanvas: true,
			axisLabelFontSizePixels: 7,
			axisLabelFontFamily: font+'Font',
			axisLabelPadding: 100,
			autoscaleMargin: 0.02,
			tickFormatter: function (v, axis) {
				return v ;
			}
		},
		legend: {
			noColumns: 100,
			labelBoxBorderColor: "#000000",
			margin: 100,
			container: true,
			sorted: false
		},
		grid: {
			hoverable: true,
			borderWidth: 1,
			backgroundColor: { colors: ["#FFF", "#FFF"] }
		}
	};
	
	var plot = $.plot($('#'+graph_id),datas, options );
	$('#' + graph_id).VUseTooltip();
	$('#' + graph_id).css("margin-left","auto");
	$('#' + graph_id).css("margin-right","auto");
	$('#' + graph_id).find('div.legend-tooltip').tooltip({ track: true });
	// Adjust the top of yaxis tick to set it in the middle of the bars
	//yAxisHeight = $('#'+graph_id+' .yAxis .tickLabel').css('height').split('px')[0];
	
	//plot.getPlaceholder().onload = function(){pruebas};
	//~ i = 0;
	//~ $('#'+graph_id+' .xAxis .tickLabel').each(function() {
		//~ $(this).css('display','none');
		//~ $(this).addClass("legend_"+i);
		//~ i++;
		//~ tickNewTop = parseInt(parseInt(tickTop) - (yAxisHeight/2)-3);
		//~ $(this).css('top', tickNewTop+'px');
		//~
		//~ valuesNewTop = parseInt(parseInt(tickTop) - (yAxisHeight));
		//~
		//~ $('#value_'+i+'_'+graph_id).css('top',parseInt(plot.offset().top) + parseInt(valuesNewTop));
//~
		//~ pixelPerValue = parseInt(plot.width()) / maxvalue;
		//~
		//~ inCanvasValuePos = parseInt(pixelPerValue * ($('#value_'+i+'_'+graph_id).html()));
		//~
		//~ $('#value_'+i+'_'+graph_id).css('left',plot.offset().left + inCanvasValuePos - $('#value_'+i+'_'+graph_id).css('width').split('px')[0] - 3);
	//~ });
	
	// When resize the window, adjust the values
	//~ $('#'+graph_id).parent().resize(function () {
		//~ i = 0;
		//~ pixelPerValue = parseInt(plot.width()) / maxvalue;
		//~
		//~ $('#'+graph_id+' .yAxis .tickLabel').each(function() {
			//~ inCanvasValuePos = parseInt(pixelPerValue * ($('#value_'+i+'_'+graph_id).html()));
			//~
			//~ $('#value_'+i+'_'+graph_id).css('left',plot.offset().left + inCanvasValuePos - $('#value_'+i+'_'+graph_id).css('width').split('px')[0] - 3);
			//~ i++;
		//~ });
	//~ });
	
	
	
	$('#'+graph_id+' .yAxis .tickLabel').each(function() {
		label = parseFloat($(this).text());
		text = label.toLocaleString();
		if ( label >= 1000000)
			text = text.substring(0,4) + "M";
		else if (label >= 100000)
				text = text.substring(0,3) + "K";
			else if (label >= 1000)
					text = text.substring(0,2) + "K";
		
		$(this).text(text);
	});
	
	$('#'+graph_id+' .xAxis .tickLabel')
		.css('transform', 'rotate(-45deg)')
		.find('div')
			.css('position', 'relative')
			.css('top', '+10px')
			.css('left', '-20px');
	
	// Format functions
	function xFormatter(v, axis) {
		var format = new Array();
		for (i = 0; i < labels_total.length; i++) {
			var label = labels_total[i][1];
			var shortLabel = reduceText(label, 35);
			var title = '';
			if (label !== shortLabel) {
				title = label;
				label = shortLabel;
			}
			format.push([i,
				'<div class="'+font+'" title="'+title+'" style="word-break: break-word; max-width: 110px;font-size:'+font_size+'pt">'
				+ label
				+ '</div>']);
		}
		return format;
	}

	function yFormatter(v, axis) {
		return v;
	}

	function lFormatter(v, axis) {
		return '<div style=color:#000>'+v+'</div>';
	}

	// Events
	//~ $('#'+graph_id).bind('plothover',  function (event, pos, item) {
		//~ $('.values_'+graph_id).css('font-weight', '');
		//~ if(item != null) {
			//~ index = item.dataIndex;
			//~ $('#value_'+index+'_'+graph_id).css('font-weight', 'bold');
		//~ }
	//~ });

	if (water_mark) {
		set_watermark(graph_id, plot, $('#watermark_image_'+graph_id).attr('src'));
	}
}

function pandoraFlotSlicebar(graph_id, values, datacolor, labels, legend, acumulate_data, intervaltick, water_mark, maxvalue, separator, separator2, graph_javascript, id_agent) {
	values = values.split(separator2);
	labels = labels.split(separator);
	legend = legend.split(separator);
	acumulate_data = acumulate_data.split(separator);
	datacolor = datacolor.split(separator);

	// Check possible adapt_keys on classes
	check_adaptions(graph_id);

	var datas = new Array();

	for (i=0;i<values.length;i++) {
		var serie = values[i].split(separator);
		var aux = new Array();
		$.each(serie,function(i,v) {
			aux.push([v, i]);
		});

		datas.push({
			data: aux,
			bars: { show: true, fill: true ,fillColor: datacolor[i] , horizontal: true, lineWidth:0, steps:false }
		});
	}

	var stack = 0, bars = true, lines = false, steps = false;

	var options = {
			series: {
				stack: stack,
				shadowSize: 0.1,
				color: '#ddd'
			},
			grid: {
				hoverable: true,
				clickable: true,
				borderWidth:1,
				borderColor: '#000',
				tickColor: '#fff'
				},
			xaxes: [ {
					tickFormatter: xFormatter,
					color: '#000',
					tickSize: intervaltick,
					tickLength: 0
					} ],
			yaxes: [ {
					show: false,
					tickLength: 0
				}],
			legend: {
				show: false
				}
		};

	var plot = $.plot($('#'+graph_id), datas, options );

	// Events
	$('#'+graph_id).bind('plothover',  function (event, pos, item) {
		if (item) {
			var from = legend[item.seriesIndex];
			var to = legend[item.seriesIndex+1];

			if (to == undefined) {
				to = '>';
			}

			$('#extra_'+graph_id).text(from+'-'+to);
			var extra_height = parseInt($('#extra_'+graph_id).css('height').split('px')[0]);
			var extra_width = parseInt($('#extra_'+graph_id).css('width').split('px')[0]);
			$('#extra_'+graph_id).css('left',pos.pageX-(extra_width/4)+'px');
			//$('#extra_'+graph_id).css('top',plot.offset().top-extra_height-5+'px');
			$('#extra_'+graph_id).show();
		}
	});

	$('#'+graph_id).bind('plotclick', function(event, pos, item) {
		if (item) {
			//from time
			var from = legend[item.seriesIndex];
			//to time
			var to = legend[item.seriesIndex+1];
			//current date
			var dateObj = new Date();
			var month = dateObj.getUTCMonth() + 1; //months from 1-12
			var day = dateObj.getUTCDate();
			var year = dateObj.getUTCFullYear();
				newdate = year + "/" + month + "/" + day;

			if(!to){
				to= '23:59';
			}
			window.location='index.php?sec=eventos&sec2=operation/events/events&id_agent='+id_agent+'&date_from='+newdate+'&time_from='+from+'&date_to='+newdate+'&time_to='+to+'&status=-1';
		}
	});



	$('#'+graph_id).bind('mouseout',resetInteractivity);

	// Reset interactivity styles
	function resetInteractivity() {
		$('#extra_'+graph_id).hide();
	}

	// Format functions
	function xFormatter(v, axis) {
		for (i = 0; i < acumulate_data.length; i++) {
			if (acumulate_data[i] == v) {
				return '<span style=\'font-size: 6pt\'>' + legend[i] + '</span>';
			}
		}
		return '';
	}
}

function pandoraFlotArea(graph_id, values, labels, labels_long, legend,
	colors, type, serie_types, water_mark, width, max_x, homeurl, unit,
	font_size, font, menu, events, event_ids, legend_events, alerts,
	alert_ids, legend_alerts, yellow_threshold, red_threshold,
	force_integer, separator, separator2, 
	yellow_up, red_up, yellow_inverse, red_inverse,
	series_suffix_str, dashboard, vconsole, xaxisname) {

	var threshold = true;
	var thresholded = false;
	font = font.split("/").pop().split(".").shift();

	values = values.split(separator2);
	serie_types = serie_types.split(separator);
	labels_long = labels_long.split(separator);
	labels = labels.split(separator);
	legend = legend.split(separator);
	events = events.split(separator);
	event_ids = event_ids.split(separator);
	if (alerts.length != 0)
		alerts = alerts.split(separator);
	else
		alerts = [];
	alert_ids = alert_ids.split(separator);
	colors = colors.split(separator);

	var eventsz = new Array();
	$.each(events,function(i,v) {
		eventsz[event_ids[i]] = v;
	});

	var alertsz = new Array();
	$.each(alerts,function(i,v) {
		alertsz[alert_ids[i]] = v;
	});

	switch (type) {
		case 'line_simple':
			stacked = null;
			filled = false;
			break;
		case 'line_stacked':
			stacked = 'stack';
			filled = false;
			break;
		case 'area_simple':
			stacked = null;
			filled = true;
			break;
		case 'area_stacked':
			stacked = 'stack';
			filled = true;
			break;
	}

	var datas = new Array();
	var data_base = new Array();

	// Prepared to turn series with a checkbox
	// var showed = new Array();

	for (i = 0; i < values.length; i++) {
		var serie = values[i].split(separator);
		var aux = new Array();
		$.each(serie, function(i, v) {
			aux.push([i, v]);
		});

		switch (serie_types[i]) {
			case 'area':
				line_show = true;
				points_show = false;
				filled = true;
				steps_chart = false;
				break;
			case 'line':
			default:
				line_show = true;
				points_show = false;
				filled = false;
				steps_chart = false;
				break;
			case 'points':
				line_show = false;
				points_show = true;
				filled = false;
				steps_chart = false
				break;
			case 'boolean':
				line_show = true;
				points_show = false;
				filled = true;
				steps_chart = true;
				break;
		}

		var serie_color;
		if (colors[i] != '') {
			serie_color = colors[i];
		}
		else {
			serie_color = '#8c2';
		}

		var normalw = '#efe';
		var warningw = '#ffe';
		var criticalw = '#fee';
		var normal = '#0f0';
		var warning = '#ff0';
		var critical = '#f00';

		// setup background areas
		//vnormal_max = vwarning_min - 1;

		var markings = null;

		// Fill the grid background with weak threshold colors
		//~ markings = [
			//~ { color: normalw, yaxis: { from: -1,to: vnormal_max } },
			//~ { color: warningw, yaxis: { from: vwarning_min, to: vwarning_max } },
			//~ { color: criticalw, yaxis: { from: vcritical_min } },
			//~ { color: criticalw, yaxis: { to: -1 } }
		//~ ];
		
		var lineWidth = $('#hidden-line_width_graph').val() || 1;

		// Data
		data_base.push({
			id: 'serie_' + i,
			data: aux,
			label: legend[i],
			color: serie_color,
			//threshold: [{ below: 80, color: "rgb(200, 20, 30)" } , { below: 65, color: "rgb(30, 200, 30)" }, { below: 50, color: "rgb(30, 200, 30)" }],
			lines: {
				show: line_show,
				fill: filled,
				fillColor: {
					colors: [ { opacity: 0.5 }, { opacity: 1 } ]
				},
				lineWidth: lineWidth,
				steps: steps_chart
			},
			points: { show: points_show }
		});

		// Prepared to turn series with a checkbox
		// showed[i] = true;
	}

	// If threshold and up are the same, that critical or warning is disabled
	if (yellow_threshold == yellow_up) yellow_inverse = false;
	if (red_threshold == red_up) red_inverse = false;

	//Array with points to be painted
	var threshold_data = new Array();
	//Array with some interesting points
	var extremes = new Array ();
	
	yellow_threshold = parseFloat (yellow_threshold);
	yellow_up = parseFloat (yellow_up);
	red_threshold = parseFloat (red_threshold);
	red_up = parseFloat (red_up);
	var yellow_only_min = ((yellow_up == 0) && (yellow_threshold != 0));
	red_only_min = ((red_up == 0) && (red_threshold != 0));
	
	if (threshold) {
		// Warning interval. Change extremes depends on critical interval
		if (yellow_inverse && red_inverse) {
			if (red_only_min && yellow_only_min) {
				// C: |--------         |
				// W: |········====     |
				
				if (yellow_threshold > red_threshold) {
					threshold_data.push({
						id: 'warning_normal_fdown',
						data: [[max_x, red_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: yellow_threshold - red_threshold, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fdown_1'] = red_threshold;
					extremes['warning_normal_fdown_2'] = yellow_threshold;
				}
			} else if (!red_only_min && yellow_only_min) {
				// C: |--------   ------|
				// W: |········===·     |
				
				if (yellow_threshold > red_up) {
					yellow_threshold = red_up;
				}
				if (yellow_threshold > red_threshold) {
					threshold_data.push({
						id: 'warning_normal_fdown',
						data: [[max_x, red_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: yellow_threshold - red_threshold, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fdown_1'] = red_threshold;
					extremes['warning_normal_fdown_2'] = yellow_threshold;
				}
			} else if (red_only_min && !yellow_only_min) {
				// C: |-------          |
				// W: |·······====   ===|
				if (red_threshold < yellow_threshold) {
					threshold_data.push({
						id: 'warning_normal_fdown',
						data: [[max_x, red_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: yellow_threshold - red_threshold, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fdown_1'] = red_threshold;
					extremes['warning_normal_fdown_2'] = yellow_threshold;
				}
				
				if (yellow_up < red_threshold) {
					yellow_up = red_threshold;
				}
				threshold_data.push({ // barWidth will be correct on draw time
					id: 'warning_up',
					data: [[max_x, yellow_up]],
					label: null,
					color: warning, 
					bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
				});
				extremes['warning_up'] = yellow_up;
				
			} else {
				if (yellow_threshold > red_threshold) {
					// C: |--------   ------|
					// W: |········===·  ···|
					if (yellow_threshold > red_up) {
						yellow_threshold = red_up;
					}
					threshold_data.push({
						id: 'warning_normal_fdown',
						data: [[max_x, red_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: yellow_threshold - red_threshold, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fdown_1'] = red_threshold;
					extremes['warning_normal_fdown_2'] = yellow_threshold;
				}
				if (yellow_up < red_up) {
					// C: |--------      ---|
					// W: |·····  ·======···|
					if (yellow_up < red_threshold) {
						yellow_up = red_up;
					}
					threshold_data.push({ 
						id: 'warning_normal_fup',
						data: [[max_x, yellow_up]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: red_up - yellow_up, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fup_1'] = red_up;
					extremes['warning_normal_fup_2'] = yellow_up;
				}
				// If warning is under critical completely do not paint anything yellow
					// C: |--------    -----|
					// W: |····          ···|
			}
		} else if (yellow_inverse && !red_inverse) {
			if (red_only_min && yellow_only_min) {
				// C: |            -----|
				// W: |============···  |
				if (yellow_threshold > red_threshold) {
					yellow_threshold = red_threshold;
				}
				threshold_data.push({ // barWidth will be correct on draw time
					id: 'warning_down',
					data: [[max_x, yellow_threshold]],
					label: null,
					color: warning, 
					bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
				});
				extremes['warning_down'] = yellow_threshold;
				
			} else if (!red_only_min && yellow_only_min) {
				// C: |      ----       |
				// W: |======····===    |
				
				if (yellow_threshold > red_up) {
					threshold_data.push({
						id: 'warning_normal_fdown',
						data: [[max_x, red_up]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: yellow_threshold - red_up, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fdown_1'] = red_up;
					extremes['warning_normal_fdown_2'] = yellow_threshold;
				}
				
				if (yellow_threshold > red_threshold) {
					yellow_threshold = red_threshold;
				}
				threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_down',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
				extremes['warning_down'] = yellow_threshold;
				
			} else if (red_only_min && !yellow_only_min) {
				if (yellow_threshold < red_threshold) {
					// C: |            -----|
					// W: |=======  ===·····|
					threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_down',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
					extremes['warning_down'] = yellow_threshold;
					
					if (red_threshold > yellow_up) {
						threshold_data.push({
							id: 'warning_normal_fup',
							data: [[max_x, yellow_up]],
							label: null,
							color: warning, 
							bars: {show: true, align: "left", barWidth: red_threshold - yellow_up, lineWidth: 0, horizontal: true}
						});
						extremes['warning_normal_fup_1'] = yellow_up;
						extremes['warning_normal_fup_2'] = red_threshold;
					}
				} else {
					// C: |     ------------|
					// W: |=====··  ········|
					threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_down',
						data: [[max_x, red_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
					extremes['warning_down'] = red_threshold;
				}
			} else {
				if (yellow_threshold > red_up) {
					// C: |    -----        |
					// W: |====·····===  ===|
					threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_down',
						data: [[max_x, red_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
					extremes['warning_down'] = red_threshold;
					
					threshold_data.push({
						id: 'warning_normal_fdown',
						data: [[max_x, red_up]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: yellow_threshold - red_up, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fdown_1'] = red_up;
					extremes['warning_normal_fdown_2'] = yellow_threshold;
					
					threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_up',
						data: [[max_x, yellow_up]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
					extremes['warning_up'] = yellow_up;
				} else if (red_threshold > yellow_up){
					// C: |          -----  |
					// W: |===    ===·····==|
					threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_down',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
					extremes['warning_down'] = yellow_threshold;
					
					threshold_data.push({
						id: 'warning_normal_fup',
						data: [[max_x, yellow_up]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: red_threshold - yellow_up, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fup_1'] = yellow_up;
					extremes['warning_normal_fup_2'] = red_threshold;
					
					threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_up',
						data: [[max_x, red_up]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
					extremes['warning_up'] = red_up;
				} else {
					// C: |  --------       |
					// W: |==·    ···=======|
					if (yellow_threshold > red_threshold) {
						yellow_threshold = red_threshold;
					}
					if (yellow_up < red_up) {
						yellow_up = red_up;
					}
					
					threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_down',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
					extremes['warning_down'] = yellow_threshold;
					
					threshold_data.push({ // barWidth will be correct on draw time
						id: 'warning_up',
						data: [[max_x, yellow_up]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
					});
					extremes['warning_up'] = yellow_up;
				}
			}
		} else if (!yellow_inverse && red_inverse) {
			if (yellow_only_min && red_only_min) {
				// C: |-----            |
				// W: |   ··============|
				if (yellow_threshold < red_threshold) {
					yellow_threshold = red_threshold;
				}
				threshold_data.push({ // barWidth will be correct on draw time
					id: 'warning_up',
					data: [[max_x, yellow_threshold]],
					label: null,
					color: warning, 
					bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
				});
				extremes['warning_up'] = yellow_threshold;
				
			} else if (!yellow_only_min && red_only_min) {
				// C: |-----            |
				// W: |   ··========    |
				if (yellow_threshold < red_threshold) {
					yellow_threshold = red_threshold;
				}
				if (yellow_up > red_threshold) {
					threshold_data.push({
						id: 'warning_normal',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: (yellow_up - yellow_threshold), lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_1'] = yellow_threshold;
					extremes['warning_normal_2'] = yellow_up;
				}
			} else if (yellow_only_min && !red_only_min) {
				// C: |-----      ------|
				// W: |   ··======······|
				if (yellow_threshold < red_threshold) {
					yellow_threshold = red_threshold;
				}
				if (yellow_threshold < red_up) {
					threshold_data.push({
						id: 'warning_normal',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: (red_up - yellow_threshold), lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_1'] = yellow_threshold;
					extremes['warning_normal_2'] = red_up;
				}
				// If warning is under critical completely do not paint anything yellow
					// C: |--------    -----|
					// W: |              ···|
			} else {
				if (red_up > yellow_threshold && red_threshold < yellow_up) {
					// C: |-----      ------|
					// W: |   ··======·     |
					if (yellow_threshold < red_threshold) {
						yellow_threshold = red_threshold;
					}
					if (yellow_up > red_up) {
						yellow_up = red_up;
					}
					
					threshold_data.push({
						id: 'warning_normal',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: (yellow_up - yellow_threshold), lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_1'] = yellow_threshold;
					extremes['warning_normal_2'] = yellow_up;
				}
			}
		}
			// If warning is under critical completely do not paint anything yellow
				// C: |--------    -----|   or	// C: |--------    -----|
				// W: |   ····          |		// W: |             ··  |
		else {
			if (red_only_min && yellow_only_min) {
				if (yellow_threshold < red_threshold) {
					// C: |        ---------|
					// W: |   =====·········|
					threshold_data.push({
						id: 'warning_normal',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: (red_threshold - yellow_threshold), lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_1'] = yellow_threshold;
					extremes['warning_normal_2'] = red_threshold;
				}
			} else if (red_only_min && !yellow_only_min) {
				// C: |        ---------|
				// W: |   =====···      |
				if (yellow_up > red_threshold) {
					yellow_up = red_threshold;
				}
				if (yellow_threshold < red_threshold) {
					threshold_data.push({
						id: 'warning_normal',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: (yellow_up - yellow_threshold), lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_1'] = yellow_threshold;
					extremes['warning_normal_2'] = yellow_up;
				}
			} else if (!red_only_min && yellow_only_min) {
				// C: |     -------     |
				// W: |   ==·······=====|
				
				if (yellow_threshold < red_threshold) {
					threshold_data.push({
						id: 'warning_normal_fdown',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: red_threshold - yellow_threshold, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fdown_1'] = yellow_threshold;
					extremes['warning_normal_fdown_2'] = red_threshold;
				}
				
				if (yellow_threshold < red_up) {
					yellow_threshold = red_up;
				}
				
				threshold_data.push({ // barWidth will be correct on draw time
					id: 'warning_up',
					data: [[max_x, yellow_threshold]],
					label: null,
					color: warning, 
					bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
				});
				extremes['warning_up'] = yellow_threshold;
				
			} else {
				if (red_threshold > yellow_threshold && red_up < yellow_up ) {
					// C: |    ------       |
					// W: |  ==······====   |
					threshold_data.push({
						id: 'warning_normal_fdown',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: red_threshold - yellow_threshold, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fdown_1'] = yellow_threshold;
					extremes['warning_normal_fdown_2'] = red_threshold;
					
					threshold_data.push({
						id: 'warning_normal_fup',
						data: [[max_x, red_up]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: yellow_up - red_up, lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_fup_1'] = red_up;
					extremes['warning_normal_fup_2'] = yellow_up;
				} else if (red_threshold < yellow_threshold && red_up > yellow_up) {
				// If warning is under critical completely do not paint anything yellow
					// C: |  --------        |
					// W: |    ····          |
				} else {
					// C: |     --------    |   or	// C: |     ------      |
					// W: |   ==··          |		// W: |        ···====  |
					if ((yellow_up > red_threshold) && (yellow_up < red_up)) {
						yellow_up = red_threshold;
					}
					if ((yellow_threshold < red_up) && (yellow_threshold > red_threshold)) {
						yellow_threshold = red_up;
					}
					threshold_data.push({
						id: 'warning_normal',
						data: [[max_x, yellow_threshold]],
						label: null,
						color: warning, 
						bars: {show: true, align: "left", barWidth: (yellow_up - yellow_threshold), lineWidth: 0, horizontal: true}
					});
					extremes['warning_normal_1'] = yellow_threshold;
					extremes['warning_normal_2'] = yellow_up;
				}
			}
		}
		// Critical interval
		if (red_inverse) {
			if (!red_only_min) {
				threshold_data.push({ // barWidth will be correct on draw time
					id: 'critical_up',
					data: [[max_x, red_up]],
					label: null,
					color: critical, 
					bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
				});
			}
			threshold_data.push({ // barWidth will be correct on draw time
				id: 'critical_down',
				data: [[max_x, red_threshold]],
				label: null,
				color: critical, 
				bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
			});
		} else {
			if (red_up == 0 && red_threshold != 0) {
				threshold_data.push({ // barWidth will be correct on draw time
					id: 'critical_up',
					data: [[max_x, red_threshold]],
					label: null,
					color: critical, 
					bars: {show: true, align: "left", barWidth: 1, lineWidth: 0, horizontal: true}
				});
			} else {
				threshold_data.push({
					id: 'critical_normal',
					data: [[max_x, red_threshold]],
					label: null,
					color: critical, 
					bars: {show: true, align: "left", barWidth: (red_up - red_threshold), lineWidth: 0, horizontal: true}
				});
			}
		}
		
	}

	// The first execution, the graph data is the base data
	datas = data_base;

	// minTickSize
	var count_data = datas[0].data.length;
	var min_tick_pixels = 80;
	var steps = parseInt( count_data / (width/min_tick_pixels));

	var options = {
			series: {
				stack: stacked,
				shadowSize: 0.1
			},
			crosshair: { mode: 'xy' },
			selection: { mode: 'x', color: '#777' },
			grid: {
				hoverable: true,
				clickable: true,
				borderWidth:1,
				borderColor: '#666',
				tickColor: '#eee',
				markings: markings
				},
			xaxes: [ {
					axisLabelFontSizePixels: font_size,
					axisLabelUseCanvas: false,
					axisLabel: xaxisname,
					tickFormatter: xFormatter,
					minTickSize: steps,
					color: '#000',
					font: font
				} ],
			yaxes: [ {
						tickFormatter: yFormatter,
						color: '#000'
					},
					{
						// align if we are to the right
						alignTicksWithAxis: 1,
						position: 'right',
						font: font
						//tickFormatter: dFormatter
					} ]
					,
			legend: {
				position: 'se',
				container: $('#legend_' + graph_id),
				labelFormatter: lFormatter
				}
		};
	if (vconsole) {
		options.grid['hoverable'] = false;
		options.grid['clickable'] = false;
		options.crosshair = false;
		options.selection = false;
	}
	
	var stack = 0, bars = true, lines = false, steps = false;

	var plot = $.plot($('#' + graph_id), datas, options);
	
	// Re-calculate the graph height with the legend height
	if (dashboard || vconsole) {
		var hDiff = $('#'+graph_id).height() - $('#legend_'+graph_id).height();
		if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ){
		}
		else {
			$('#'+graph_id).css('height', hDiff);
		}
	}
	
	if (vconsole) {
		var myCanvas = plot.getCanvas();
		plot.setupGrid(); // redraw plot to new size
		plot.draw();
		var image = myCanvas.toDataURL("image/png");
		return;
	}
	
	// Adjust the overview plot to the width and position of the main plot
	adjust_left_width_canvas(graph_id, 'overview_'+graph_id);

	// Adjust linked graph to the width and position of the main plot

	// Miniplot
	var overview = $.plot($('#overview_'+graph_id),datas, {
		series: {
			stack: stacked,
			lines: { show: true, lineWidth: 1 },
			shadowSize: 0
		},
		grid: { borderWidth: 1, hoverable: true, autoHighlight: false},
		xaxis: { },
			xaxes: [ {
				tickFormatter: xFormatter,
				minTickSize: steps,
				color: '#000'
				} ],
		yaxis: {ticks: [], autoscaleMargin: 0.1 },
		selection: {mode: 'x', color: '#777' },
		legend: {show: false},
		crosshair: {mode: 'x'}
	});

	// Connection between plot and miniplot

	$('#' + graph_id).bind('plotselected', function (event, ranges) {
		// do the zooming if exist menu to undo it
		if (menu == 0) {
			return;
		}
		dataInSelection = ranges.xaxis.to - ranges.xaxis.from;
		dataInPlot = plot.getData()[0].data.length;

		factor = dataInSelection / dataInPlot;

		new_steps = parseInt(factor * steps);

		plot = $.plot($('#' + graph_id), data_base,
			$.extend(true, {}, options, {
				xaxis: { min: ranges.xaxis.from, max: ranges.xaxis.to},
				xaxes: [ {
						tickFormatter: xFormatter,
						minTickSize: new_steps,
						color: '#000'
						} ],
				legend: { show: false }
			}));
		if (thresholded) {
			var zoom_data_threshold = new Array ();
			
			zoom_data_threshold = add_threshold (data_base, threshold_data, plot.getAxes().yaxis.min, plot.getAxes().yaxis.max,
										yellow_threshold, red_threshold, extremes, red_up);
			plot.setData(zoom_data_threshold);
			plot.draw();
		}
			

		$('#menu_cancelzoom_' + graph_id)
			.attr('src', homeurl + '/images/zoom_cross_grey.png');

		currentRanges = ranges;
		// don't fire event on the overview to prevent eternal loop
		overview.setSelection(ranges, true);
	});

	$('#overview_' + graph_id)
		.bind('plotselected', function (event, ranges) {
			plot.setSelection(ranges);
		});

	var legends = $('#legend_' + graph_id + ' .legendLabel');


	var updateLegendTimeout = null;
	var latestPosition = null;
	var currentPlot = null;
	var currentRanges = null;

	// Update legend with the data of the plot in the mouse position
	function updateLegend() {
		updateLegendTimeout = null;

		var pos = latestPosition;

		var axes = currentPlot.getAxes();
		if (pos.x < axes.xaxis.min || pos.x > axes.xaxis.max ||
			pos.y < axes.yaxis.min || pos.y > axes.yaxis.max) {
			return;
		}

		var j, dataset = currentPlot.getData();

		var i = 0;
		for (k = 0; k < dataset.length; k++) {

			// k is the real series counter
			// i is the series counter without thresholds
			var series = dataset[k];

			if (series.label == null) {
				continue;
			}

			// find the nearest points, x-wise
			for (j = 0; j < series.data.length; ++j)
				if (series.data[j][0] > pos.x) {
					break;
				}

			var y = series.data[j][1];
			var how_bigger = "";
			if (y > 1000000) {
				how_bigger = "M";
				y = y / 1000000;
			}
			else if (y > 1000) {
				how_bigger = "K";
				y = y / 1000;
			}

			if (currentRanges == null || (currentRanges.xaxis.from < j && j < currentRanges.xaxis.to)) {
				$('#timestamp_'+graph_id).show();
				// If no legend, the timestamp labels are short and with value
				if (legends.length == 0) {
					$('#timestamp_'+graph_id).text(labels[j] + ' (' + parseFloat(y).toFixed(2) + ')');
				}
				else {
					$('#timestamp_'+graph_id).text(labels_long[j]);
				}

				//$('#timestamp_'+graph_id).css('top', plot.offset().top-$('#timestamp_'+graph_id).height()*1.5);

				var timesize = $('#timestamp_'+graph_id).width();

				if (currentRanges != null) {
					dataset = plot.getData();
				}

				var timenewpos = dataset[0].xaxis.p2c(pos.x)+$('.yAxis>div').eq(0).width();

				var canvaslimit = plot.width();

				if (timesize+timenewpos > canvaslimit) {
					$('#timestamp_'+graph_id).css('left', timenewpos - timesize);
				}
				else {
					$('#timestamp_'+graph_id).css('left', timenewpos);
				}
			}
			else {
				$('#timestamp_'+graph_id).hide();
			}

			var label_aux = series.label;
			
			// The graphs of points type and unknown graphs will dont be updated
			if (serie_types[i] != 'points' && series.label != $('#hidden-unknown_text').val()) {
				$('#legend_' + graph_id + ' .legendLabel')
					.eq(i).html(label_aux +	'= ' + parseFloat(y).toFixed(precision_graph) + how_bigger + ' ' + unit);
				console.log($('#legend_' + graph_id + ' .legendLabel'));
			}

			$('#legend_' + graph_id + ' .legendLabel')
				.eq(i).css('font-size',font_size+'pt');

			$('#legend_' + graph_id + ' .legendLabel')
				.eq(i).css('color','#000');

			//~ $('#legend_' + graph_id + ' .legendLabel')
				//~ .eq(i).css('font-family',font+'Font');

			i++;
		}
	}

	// Events
	$('#' + graph_id).bind('plothover',  function (event, pos, item) {
		overview.setCrosshair({ x: pos.x, y: 0 });
		currentPlot = plot;
		latestPosition = pos;
		if (!updateLegendTimeout) {
			updateLegendTimeout = setTimeout(updateLegend, 50);
		}

	});

	$('#' + graph_id).bind("plotclick", function (event, pos, item) {
		plot.unhighlight();
		if (item && item.series.label != '' && (item.series.label == legend_events || item.series.label == legend_events+series_suffix_str || item.series.label == legend_alerts || item.series.label == legend_alerts+series_suffix_str)) {
			plot.unhighlight();
			var canvaslimit = parseInt(plot.offset().left + plot.width());
			var dataset  = plot.getData();
			var timenewpos = parseInt(dataset[0].xaxis.p2c(pos.x)+plot.offset().left);
			var extrasize = parseInt($('#extra_'+graph_id).css('width').split('px')[0]);

			var left_pos;
			if (extrasize+timenewpos > canvaslimit) {
				left_pos = timenewpos - extrasize - 20;
			}
			else {
				left_pos = timenewpos - (extrasize / 2);
			}

			var extra_info = '<i>No info to show</i>';
			var extra_show = false;

			$('#extra_'+graph_id).css('left',left_pos);
			$('#extra_'+graph_id).css('top',plot.offset().top + 25);

			switch(item.series.label) {
				case legend_alerts+series_suffix_str:
				case legend_alerts:
					extra_info = '<b>'+legend_alerts+':<br><span style="font-size:xx-small; font-weight: normal;">From: '+labels_long[item.dataIndex];
					if (labels_long[item.dataIndex+1] != undefined) {
						extra_info += '<br>To: '+labels_long[item.dataIndex+1];
					}
					extra_info += '</span></b>'+get_event_details(alertsz[item.dataIndex]);
					extra_show = true;
					break;
				case legend_events+series_suffix_str:
				case legend_events:
					extra_info = '<b>'+legend_events+':<br><span style="font-size:xx-small; font-weight: normal;">From: '+labels_long[item.dataIndex];
					if (labels_long[item.dataIndex+1] != undefined) {
						extra_info += '<br>To: '+labels_long[item.dataIndex+1];
					}
					extra_info += '</span></b>'+get_event_details(eventsz[item.dataIndex]);
					extra_show = true;
					break;
				default:
					return;
					break;
			}

			if (extra_show) {
				$('#extra_'+graph_id).html(extra_info);
				$('#extra_'+graph_id).css('display','');
			}
			plot.highlight(item.series, item.datapoint);
		}
		else {
			$('#extra_'+graph_id).html('');
			$('#extra_'+graph_id).css('display','none');
		}
	});

	$('#overview_'+graph_id).bind('plothover',  function (event, pos, item) {
		plot.setCrosshair({ x: pos.x, y: 0 });
		currentPlot = overview;
		latestPosition = pos;
		if (!updateLegendTimeout) {
			updateLegendTimeout = setTimeout(updateLegend, 50);
		}
	});

	$('#'+graph_id).bind('mouseout',resetInteractivity);
	$('#overview_'+graph_id).bind('mouseout',resetInteractivity);
	
	// Reset interactivity styles
	function resetInteractivity() {
		$('#timestamp_'+graph_id).hide();
		dataset = plot.getData();
		for (i = 0; i < dataset.length; ++i) {
			var series = dataset[i];
			var label_aux = series.label;
			$('#legend_' + graph_id + ' .legendLabel')
				.eq(i).html(label_aux);
		}
		plot.clearCrosshair();
		overview.clearCrosshair();
	}

	// Format functions
	function xFormatter(v, axis) {
		if (labels[v] == undefined) {
			return '';
		}
		return '<div class='+font+' style=font-size:'+font_size+'pt>'+labels[v]+'</div>';
	}

	function yFormatter(v, axis) {
		var formatted = number_format(v,force_integer,unit);

		return '<div class='+font+' style=font-size:'+font_size+'pt>'+formatted+'</div>';
	}

	function lFormatter(v, item) {
		return '<div style=color:#000;font-size:'+font_size+'pt>'+v+'</div>';
		// Prepared to turn series with a checkbox
		//return '<div style=color:#000;font-size:'+font_size+'pt><input type="checkbox" id="' + graph_id + '_' + item.id +'" checked="checked" class="check_serie_'+graph_id+'">'+v+'</div>';
	}

	// Used to export the graph data to a file.
	// Uses plot, labels and labels_long as scope variables.
	function exportData (options) {
		options = options || {};

		// Options
		var type = options.type || 'csv';
		type = type.toLowerCase().trim();

		var graphData,
			dataObject,
			dataObjects = plot.getData(),
			result = [];

		// Throw errors
		var retrieveDataOject = function (dataObjects) {
			var result;

			if (typeof dataObjects === 'undefined')
				throw new Error('Empty parameter');

			// Try to retrieve the avg set (not 100% reliable, I know)
			if (dataObjects.length == 1) {
				result = dataObjects.shift();
			}
			if (dataObjects.length > 1) {
				dataObjects.forEach(function (element) {
					if (/^Avg.:/i.test(element.label))
						result = element;
				});

				// If the avg set is missing, retrieve the first set
				if (typeof result === 'undefined')
					result = dataObjects.shift();
			}

			if (typeof result === 'undefined')
				throw new Error('Empty result');

			return result;
		}

		// Throw errors
		var processDataObject = function (dataObject) {
			var result;

			if (typeof dataObject === 'undefined')
				throw new Error('Empty parameter');

			if (typeof dataObject.data === 'undefined'
					|| !(dataObject.data instanceof Array))
				throw new Error('Object malformed');

			/* {
			 *   head: [<column>,<column>,...,<column>],
			 *   data: [
			 *     [<data>,<data>,...,<data>],
			 *     [<data>,<data>,...,<data>],
			 *     ...,
			 *     [<data>,<data>,...,<data>],
			 *   ]
			 * }
			 */
			if (type === 'csv') {

				result = {
					head: ['date', 'value','label'],
					data: []
				};

				dataObject.data.forEach(function (item, index) {
					var date = '', value = item[1];

					// Long labels are preferred
					if (typeof labels_long[index] !== 'undefined')
						date = labels_long[index];
					else if (typeof labels[index] !== 'undefined')
						date = labels[index];

					result.data.push([date, value,dataObject.label]);
				});
			}
			/* [
			 *   {
			 *     'date': <date>,
			 *     'value': <value>
			 *   }
			 * ],
			 * [
			 *   {
			 *     'date': <date>,
			 *     'value': <value>
			 *   }
			 * ],
			 * ...,
			 * [
			 *   {
			 *     'date': <date>,
			 *     'value': <value>
			 *   }
			 * ]
			 */
			else if (type === 'json') {
				result = [];

				dataObject.data.forEach(function (item, index) {
					var date = '', value = item[1];

					// Long labels are preferred
					if (typeof labels_long[index] !== 'undefined')
						date = labels_long[index];
					else if (typeof labels[index] !== 'undefined')
						date = labels[index];

					result.push({
						'date': date,
						'value': value,
						'label': dataObject.label
					});
				});
			}

			if (typeof result === 'undefined')
				throw new Error('Empty result');

			return result;
		}

		try {
			var elements = [];
			var custom_graph = $('input:hidden[name=custom_graph]').value;

			if (custom_graph) {
				dataObject = retrieveDataOject(dataObjects);
				dataObjects.forEach(function (element) {
					elements.push(processDataObject(element));
				});
				graphData = elements;
			}
			else {
				dataObject = retrieveDataOject(dataObjects);
				elements.push(processDataObject(dataObject));
				graphData = elements;
			}

			// Transform the object data into a string
			// cause PHP has limitations in the number
			// of POST params received.
			var graphDataStr = JSON.stringify(graphData);

			// Build form
			var $form = $('<form></form>'),
				$dataInput = $('<input>'),
				$typeInput = $('<input>'),
				$separatorInput = $('<input>'),
				$excelInput = $('<input>');

			$dataInput
				.prop('name', 'data')
				.prop('type', 'text')
				.prop('value', graphDataStr);

			$typeInput
				.prop('name', 'type')
				.prop('type', 'text')
				.prop('value', type);

			$separatorInput
				.prop('name', 'separator')
				.prop('type', 'text')
				.prop('value', ';');

			$excelInput
				.prop('name', 'excel_encoding')
				.prop('type', 'text')
				.prop('value', 0);

			$form
				.prop('method', 'POST')
				.prop('action', homeurl + '/include/graphs/export_data.php')
				.append($dataInput, $typeInput, $separatorInput, $excelInput)
				.hide()
				// Firefox made me write into the DOM for this :(
				.appendTo('body')
				.submit();
		}
		catch (e) {
			alert('There was an error exporting the data');
			console.log(e);
		}
	}

	// Prepared to turn series with a checkbox
	//~ $('.check_serie_'+graph_id).click(function() {
		//~ // Format of the id is graph_3905jf93f03_serie_id
		//~ id_clicked = this.id.split('_')[3];
		//~ // Update the serie clicked
		//~ showed[id_clicked] = this.checked;
	//~ });

	if (menu) {
		var parent_height;
		$('#menu_overview_' + graph_id).click(function() {
			$('#overview_' + graph_id).toggle();
		});

		$('#menu_export_csv_' + graph_id).click(function() {
			exportData({ type: 'csv' });
		});

		$('#menu_export_json_' + graph_id).click(function() {
			exportData({ type: 'json' });
		});

		$('#menu_threshold_' + graph_id).click(function() {
			datas = new Array();

			if (thresholded) {
				$.each(data_base, function() {
					// Prepared to turning series
					//if(showed[this.id.split('_')[1]]) {
						datas.push(this);
					//}
				});
				plot = $.plot($('#' + graph_id), data_base,
					$.extend(true, {}, options, {
						yaxis: {max: max_draw},
					}));
				thresholded = false;
			}
			else {
				
				var max_draw = plot.getAxes().yaxis.datamax;
				if (max_draw < red_threshold || max_draw < yellow_threshold) {
					
					var maxim_data = (red_threshold < yellow_threshold) ?  yellow_threshold : red_threshold
					
					plot = $.plot($('#' + graph_id), data_base,
					$.extend(true, {}, options, {
						yaxis: {max: maxim_data + (maxim_data*0.5)},
					}));
				}
				datas = add_threshold (data_base, threshold_data, plot.getAxes().yaxis.min, plot.getAxes().yaxis.max,
										yellow_threshold, red_threshold, extremes, red_up);
				thresholded = true;
				
			}
			
			plot.setData(datas);
			plot.draw();

			//~ plot.setSelection(currentRanges);
		});

		$('#menu_cancelzoom_' + graph_id).click(function() {
			// cancel the zooming
			plot = $.plot($('#' + graph_id), data_base,
				$.extend(true, {}, options, {
					xaxis: {max: max_x },
					legend: { show: false }
				}));

			$('#menu_cancelzoom_' + graph_id)
				.attr('src', homeurl + '/images/zoom_cross.disabled.png');
			overview.clearSelection();
			currentRanges = null;
			
			thresholded = false;
		});

		// Adjust the menu image on top of the plot
		// If there is no legend we increase top-padding to make space to the menu
		if (legends.length == 0) {
			$('#menu_' + graph_id).parent().css('padding-top',
				$('#menu_' + graph_id).css('height'));
		}

		// Add bottom margin in the legend
		// Estimated height of 24 (works fine with this data in all browsers)
		menu_height = 24;
		var legend_margin_bottom = parseInt(
			$('#legend_'+graph_id).css('margin-bottom').split('px')[0]);
		$('#legend_'+graph_id).css('margin-bottom', '10px');
		parent_height = parseInt(
			$('#menu_'+graph_id).parent().css('height').split('px')[0]);
		adjust_menu(graph_id, plot, parent_height);
	}

	if (water_mark) {
		set_watermark(graph_id, plot, $('#watermark_image_'+graph_id).attr('src'));
	}

	adjust_menu(graph_id, plot, parent_height);
}

function adjust_menu(graph_id, plot, parent_height) {
	if ($('#'+graph_id+' .xAxis .tickLabel').eq(0).css('width') != undefined) {
		left_ticks_width = $('#'+graph_id+' .xAxis .tickLabel').eq(0).css('width').split('px')[0];
	}
	else {
		left_ticks_width = 0;
	}

	var parent_height_new = 0;

	var legend_height = parseInt($('#legend_'+graph_id).css('height').split('px')[0]) + parseInt($('#legend_'+graph_id).css('margin-top').split('px')[0]);
	if ($('#overview_'+graph_id).css('display') == 'none') {
		overview_height = 0;
	}
	else {
		overview_height = parseInt($('#overview_'+graph_id).css('height').split('px')[0]) + parseInt($('#overview_'+graph_id).css('margin-top').split('px')[0]);
	}

	var menu_height = '25';

	if ($('#menu_'+graph_id).height() != undefined && $('#menu_'+graph_id).height() > 20) {
		menu_height = $('#menu_'+graph_id).height();
	}

	offset_between_graph_and_div_graph_container = $('#' + graph_id).offset().top -
		$('#' + graph_id).parent().offset().top;
	$('#menu_' + graph_id)
		.css('top',
			((offset_between_graph_and_div_graph_container - menu_height - 5) + 'px'));

	//$('#legend_' + graph_id).css('width',plot.width());

	$('#menu_' + graph_id)
		.css('left',plot.width() - $('#menu_'+graph_id).width() + 10);
	$('#menu_' + graph_id).show();
}

function set_watermark(graph_id, plot, watermark_src) {
	var img = new Image();
	img.src = watermark_src;
	var context = plot.getCanvas().getContext('2d');

	// Once it's loaded draw the image on the canvas.
	img.addEventListener('load', function () {
		//~ // Now resize the image: x, y, w, h.

		var down_ticks_height = 0;
		if ($('#'+graph_id+' .yAxis .tickLabel').eq(0).css('height') != undefined) {
			down_ticks_height = $('#'+graph_id+' .yAxis .tickLabel').eq(0).css('height').split('px')[0];
		}
		var left_pos = parseInt(context.canvas.width - 3) - $('#watermark_image_'+graph_id)[0].width;
		var top_pos = parseInt(context.canvas.height - down_ticks_height - 20) - $('#watermark_image_'+graph_id)[0].height;

		context.drawImage(this, left_pos, top_pos);

	}, false);
}

function get_event_details (event_ids) {
	table = '';
	if (typeof(event_ids) != "undefined") {
		var inputs = [];
		var table;
		inputs.push ("get_events_details=1");
		inputs.push ("event_ids="+event_ids);
		inputs.push ("page=include/ajax/events");

		// Autologin
		if ($('#hidden-loginhash').val() != undefined) {
			inputs.push ("loginhash=" + $('#hidden-loginhash').val());
			inputs.push ("loginhash_data=" + $('#hidden-loginhash_data').val());
			inputs.push ("loginhash_user=" + $('#hidden-loginhash_user').val());
		}

		jQuery.ajax ({
			data: inputs.join ("&"),
			type: 'GET',
			url: action="../../ajax.php",
			timeout: 10000,
			dataType: 'html',
			async: false,
			success: function (data) {
				table = data;
				//forced_title_callback();
			}
		});
	}

	return table;
}

function adjust_left_width_canvas(adapter_id, adapted_id) {
	var adapter_left_margin = $('#'+adapter_id+' .yAxis .tickLabel').width();
	var adapted_pix = $('#'+adapted_id).width();
	var new_adapted_width = adapted_pix - adapter_left_margin;
	
	$('#'+adapted_id).width(new_adapted_width);
	$('#'+adapted_id).css('margin-left', adapter_left_margin);
}

function check_adaptions(graph_id) {
	var classes = $('#'+graph_id).attr('class').split(' ');

	$.each(classes, function(i,v) {
		// If has a class starting with adapted, we adapt it
		if (v.split('_')[0] == 'adapted') {
			var adapter_id = $('.adapter_'+v.split('_')[1]).attr('id');
			adjust_left_width_canvas(adapter_id, graph_id);
		}
	});
}

function number_format(number, force_integer, unit) {
	if (force_integer) {
		if (Math.round(number) != number) {
			return '';
		}
	}
	else {
		var decimals = 2;
		var factor = 10 * decimals;
		number = Math.round(number*factor)/factor;
	}

	var shorts = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
	var pos = 0;
	while (1) {
		if (number >= 1000) { //as long as the number can be divided by 1000
			pos++; //Position in array starting with 0
			number = number / 1000;
		}
		else {
			break;
		}
	}
	
	return number + ' ' + shorts[pos] + unit;
}
function add_threshold (data_base, threshold_data, y_min, y_max, yellow_threshold,
						red_threshold, extremes, red_up) {
	
	var datas = new Array ();
	
	$.each(data_base, function() {
		// Prepared to turning series
		//if(showed[this.id.split('_')[1]]) {
			datas.push(this);
		//}
	});

	// Resize the threshold data
	$.each(threshold_data, function() {
		if (/_up/.test(this.id)){
			this.bars.barWidth = y_max - this.data[0][1];
		}
		if (/_down/.test(this.id)){
			var end;
			if (/critical/.test(this.id)) {
				 end = red_threshold;
			} else {
				end = extremes[this.id];
			}
			this.bars.barWidth = end - y_min;
			this.data[0][1] = y_min;
		}
		if (/_normal/.test(this.id)){
			var end;
			if (/critical/.test(this.id)) {
				end = red_up;
			} else {
				end = extremes[this.id + '_2'];
			}
			if (this.data[0][1] < y_min) {
				this.bars.barWidth = end - y_min;
				this.data[0][1] = y_min;
				end = this.bars.barWidth + this.data[0][1];
			}
			if (end > y_max) {
				this.bars.barWidth = y_max - this.data[0][1];
			}
		}	
		datas.push(this);
	});
	
	return datas;
}

function reduceText (text, maxLength) {
	if (text.length <= maxLength) return text
	var firstSlideEnd = parseInt((maxLength - 3) / 2);
	return text.substr(0, firstSlideEnd) + '...' + text.substr(-firstSlideEnd - 3);
}
