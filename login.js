
$(document).ready(function()
{
	"use strict";
	$("#LoginUsernameField, #LoginPasswordField").keydown( function(event)
	{ 
		if ( event.which == 13 ) // Return key
		{
			SubmitLogin();
		}
	});
});

function SubmitLogin()
{
	"use strict";
	
	var Username = $("#LoginUsernameField").val();
	var Password = $("#LoginPasswordField").val();

	if ( Password == null || Password == ''
	  || Username == null || Username == '')
	{
		return;
	}
	
	var form = document.createElement("form");
	form.setAttribute("method", "post");
	form.setAttribute("action", "index.php");
	
	var hiddenField = document.createElement("input");
	hiddenField.setAttribute("type", "hidden");
	hiddenField.setAttribute("name", "username");
	hiddenField.setAttribute("value", Username);
	
	form.appendChild(hiddenField);
	
	hiddenField = document.createElement("input");
	hiddenField.setAttribute("type", "hidden");
	hiddenField.setAttribute("name", "password");
	hiddenField.setAttribute("value", Password);
	
	form.appendChild(hiddenField);
	
	document.body.appendChild(form);
    form.submit();
}