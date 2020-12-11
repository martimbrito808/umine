$(document).ready(function(){
	getData();
});

function getData(){
	$.ajax({
		type: "get",
		async: false, //同步执行 
		url: "//jinhe/index.php/Store/Home/getChartsData",
		dataType: "json", //返回数据形式为json 
		success: function (res) {
			if(res.error == 0){
				//初讯债券收益率走势图
				if(res.data.data1){
					var data1_x = res.data.data1.x;
					var data1_y = res.data.data1.y;
					echart1(data1_x, data1_y);
				}				
			}else{
				//alert(res.msg);
			}
		},
		error: function () {
			//alert("请求数据失败!");
		}
	});
}

function echart1(datax, datay){
	var myChart = echarts.init(document.getElementById('echarts1'));
	var option = {
		tooltip : {
			trigger: 'axis',
			formatter:'日期：{b}<br>收益率：{c}%',
			axisPointer: {
				lineStyle: {
					color: '#dddc6b'
				}
			}
		},
		legend: {
			data: "[{name:'123434324'}]",
		},
		grid: {
			left: '30',
			top: '50',
			right: '50',
			bottom: '10',
			containLabel: true
		},
		xAxis: [{
			type: 'category',
			boundaryGap: false,
			axisLabel:  {
				textStyle: {
					color: "rgba(0,0,0,.6)",
					fontSize:12,
				},
			},
			axisLine: {
				lineStyle: { 
					color: 'rgba(0,0,0,.2)'
				}
			},
			data: datax

		}, {
			axisPointer: {show: false},
			axisLine: {  show: false},
			position: 'bottom',
			offset: 20,
		}],

		yAxis: [{
			type: 'value',
			axisTick: {show: false},
			axisLine: {
				lineStyle: {
					color: 'rgba(0,0,0,.1)'
				}
			},
			axisLabel:  {
				textStyle: {
					color: "rgba(0,0,0,.6)",
					fontSize:12,
				},
			},

			splitLine: {
				lineStyle: {
					 color: 'rgba(0,0,0,.1)'
				}
			}
		}],
		series: [{
			type: 'line',
			smooth: true,
			symbol: 'circle',
			symbolSize: 5,
			showSymbol: false,
			lineStyle: {				
				normal: {
					color: '#0184d5',
					width: 2
				}
			},
			areaStyle: {
				normal: {
					color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
						offset: 0,
						color: 'rgba(1, 132, 213, 0.4)'
					}, {
						offset: 0.8,
						color: 'rgba(1, 132, 213, 0.1)'
					}], false),
					shadowColor: 'rgba(0, 0, 0, 0.1)',
				}
			},
			itemStyle: {
				normal: {
					color: '#0184d5',
					borderColor: 'rgba(221, 220, 107, .1)',
					borderWidth: 12
				}
			},
			data: datay
		}]
	};
	
	myChart.setOption(option);
	window.addEventListener("resize",function(){
		myChart.resize();
	});
}