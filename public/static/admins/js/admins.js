//左侧菜单效果
$('.navlink').click(function (event) {
	var url = $(this).children('a').attr('data-url');
	var title = $(this).find('a').html();
	var index = $(this).children('a').attr('data-id');

	for (var i = 0; i <$('.iframe').length; i++) {
		if($('.iframe').eq(i).attr('tab-id') == index){
			tab.tabChange(index);
			event.stopPropagation();
			return;
		}
	};
	
	tab.tabAdd(title, url, index);
	tab.tabChange(index);
	   
	event.stopPropagation();         
})

//菜单隐藏显示
$('#hideBtn').on('click', function() {
	if(!$('#main-layout').hasClass('hide-side')) {
		$('#main-layout').addClass('hide-side');
	} else {
		$('#main-layout').removeClass('hide-side');
	}
});
//遮罩点击隐藏
$('.main-mask').on('click', function() {
	$('#main-layout').removeClass('hide-side');
})