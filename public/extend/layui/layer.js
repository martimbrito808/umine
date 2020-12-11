$(function () {
	//加载弹出层
    layui.use(['layer', 'element'], function() {
        layer = layui.layer;
        element = layui.element;
    });    
})

/*弹出层*/
/*
    参数解释：
    title   标题
    url     请求的url
    id      需要操作的数据id
    w       弹出层宽度（缺省调默认值）
    h       弹出层高度（缺省调默认值）
*/
function showWin(title, url){
    if (title == null || title == '') {
        title=false;
    };
    if (url == null || url == '') {
        url="404.html";
    };
    var index = layer.open({
        type: 2,
		skin: 'mypop',
        area: [$(window).width()+'px', $(window).height() +'px'],
        fix: false, //不固定
        maxmin: true,
        shadeClose: false,
        shade:0.4,
        title: title,
        content: url,
		end: function(index, layero){
			$(".layui-laypage-btn")[0].click();
		} 
    });
	layer.full(index);
}

/*
    参数解释：
    title   标题
    url     请求的url
    id      需要操作的数据id
    w       弹出层宽度（缺省调默认值）
    h       弹出层高度（缺省调默认值）
*/
function showDiyWin(title, url, w, h){
    if (title == null || title == '') {
        title=false;
    };
    if (url == null || url == '') {
        url="404.html";
    };
    if (w == null || w == '') {
        w=($(window).width()*0.8);
    };
    if (h == null || h == '') {
        h=($(window).height()-100);
    };
    var index = layer.open({
        type: 2,
		skin: 'mypop',
        area: [w+'px', h +'px'],
        fix: false, //不固定
        maxmin: true,
        shadeClose: false,
        shade:0.4,
        title: title,
        content: url,
		end: function(index, layero){
			$(".layui-laypage-btn")[0].click();
		} 
    });
}

/*加载提示层*/
function loading(msg){
	layer.msg(msg, {
		icon:16,
		time:false  //取消自动关闭
	})
}

/*提示框*/
function msg(msg){
	layer.msg(msg, {time:1000});
};

/*关闭弹出框口*/
function closeWin(){
    var index = parent.layer.getFrameIndex(window.name);
    parent.layer.close(index);
	$(".layui-laypage-btn")[0].click();
}

/*关闭弹出框口并刷新页面*/
function closeTop(){
    var index = parent.layer.getFrameIndex(window.name);
    parent.layer.close(index);
	parent.location.reload();
}

/*确认框*/
function affirm(content, url){
	layer.confirm(content, {icon: 3, title:'确认您的操作'}, function(index){
		layer.close(index);
		location.href = url;
	});
}
