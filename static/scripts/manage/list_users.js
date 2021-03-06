$('#users table.item-list tbody')
	.selectable({ filter: 'tr', cancel: 'tr#unew, a' });

function rename_user(result)
{
	var $item = $('tr#u'+result.id);
	if (result.state != 'failed')
	{
		$item.find("td.username > a:first-child").text(result.username).end()
			.find("td.login").text(result.login).end()
			.find("td.email a").attr("href", "mailto:" + result.email).text(result.email);
	}
	else
	{
		error_form(result, $item);
	}
}

function remove_user(result)
{
	remove_element($("table.item-list tr#u"+result.id));
}

enable_context_menu('table.item-list tbody tr td.username a', '#users-menu', function(action, item, pos){

var $href = $(item);
var $item = $(item).parent().parent();
var itemid = parseInt($item.attr("id").substring(1));
if (isNaN(itemid)) itemid = 0;

switch (action)
{
case 'edit':
	var pos = $item.offset();
	pos.top += $href.height();
	$("#rename-user")
		.attr("action", sitePrefix+"/user/rename/"+itemid)
		.data("ajaxaction", rename_user)
		.css(pos)
		.find("#uusername").val($href.text()).end()
		.find("#ulogin").val($item.find("td.login").text()).end()
		.find("#uemail").val($item.find("td.email").text()).end()
		.show()
		.find("#uusername").focus();
break;
case 'cut':
	$('table.item-list tbody tr.cut').removeClass('cut');
	$('table.item-list tbody tr.ui-selected').addClass('cut');
	$('.context-menu li.paste').removeClass('disabled');
	$item.addClass('cut');
break;
case 'remove':
	ask_question("Точно удалить этого пользователя?", $href, sitePrefix+"/user/remove/"+itemid, remove_user);
break;
}
});


$('.item-list tbody tr td.controls a.remove').click(function(){ask_question("Точно удалить этого пользователя?", $(this), $(this).attr('href'), remove_user); return false;});
$('#group-controls a.remove').click(function(){ask_question("Точно удалить эту группу?", $(this), $(this).attr('href'), function(){window.location = sitePrefix+"/group/"}); return false;});
