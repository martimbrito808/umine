$(function () {
	$.ajax({
		type: "get",
		async: false, //同步执行 
		url: "//xshuju.com/index.php/Home/Index/getArea",
		dataType: "json", //返回数据形式为json 
		success: function (res) {
			if(res.error == 0){
				//初讯债券收益率走势图				
				map(res.data.store);						
			}else{
				//alert(res.msg);
			}
		},
		error: function () {
			//alert("MAP请求数据失败!");
		}
	});	
	
    function map(store) {
        // 基于准备好的dom，初始化echarts实例
        var myChart = echarts.init(document.getElementById('map_1'));
		var data = store;
		var convertData = function (data) {
			var res = [];
			for (var i = 0; i < data.length; i++) {
				var geoCoord = [data[i].lng, data[i].lat];
				if (geoCoord) {
					res.push({
						name: data[i].name,
						value: geoCoord.concat(data[i].value)
					});
				}
			}
			return res;
		};

		option = {
			tooltip : {
				trigger: 'item',
				formatter: function (params) {
					  if(typeof(params.value)[2] == "undefined"){
						return params.name + ' : ' + params.value;
					  }else{
						return params.name + ' : ' + params.value[2];
					  }
					}
			},
		  
			geo: {
				map: 'china',
				label: {
					emphasis: {
						show: false
					}
				},
				roam: false,//禁止其放大缩小
				itemStyle: {
					normal: {
						areaColor: '#4c60ff',
						borderColor: '#002097'
					},
					emphasis: {
						areaColor: '#293fff'
					}
				}
			},
			series : [
				{
					name: '消费金额',
					type: 'scatter',
					coordinateSystem: 'geo',
					data: convertData(data),
					symbolSize: function (val) {
						return val[2] / 5;
					},
					label: {
						normal: {
							formatter: '{b}',
							position: 'right',
							show: false
						},
						emphasis: {
							show: true
						}
					},
					itemStyle: {
						normal: {
							color: '#ffeb7b'
						}
					}
				}

			]
		};
		
        myChart.setOption(option);
        window.addEventListener("resize",function(){
            myChart.resize();
        });
    }

})

