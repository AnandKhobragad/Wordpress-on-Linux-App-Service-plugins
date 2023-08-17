jQuery(function ($) {
	var ajaxurl = azure_app_service_migration.ajaxurl;
  
	// Ajax call before function
	function pagebeforeloadresonse() {
	  $("#downloadfile").hide();
	  //$("body").addClass("loading");
	  $("#blinkdata").show();
	}
	var blinkInterval;
	var blinkTimeout;
	// Processing event on button click
	$("#generatefile").click(function () {
		stopBlinking($("#exportdownloadfile"));
		$("#exportdownloadfile").hide();
		$('#downloadLink').hide();
		$(this).prop("disabled", true).text("Generating Export...");
	
		var postdata = $("#frm-chkbox-data").serialize();
		postdata += "&action=aasm_export&param=wp_filebackup";
		postdata += "&is_first_request=true";

		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: postdata,
			dataType: "json",
			success: function (data) {
				console.log(data);
				if (data.status == 1) {
					showAlert(data.message);
					$("#exportdownloadfile").show();
					$('#downloadLink').show().css('display', 'inline-block');
					blinkElement("#exportdownloadfile");
					$('#exportdownloadfile').load(window.location.href + ' #exportdownloadfile');
				} else {
					showAlert(data.message);
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				console.log("AJAX request failed: " + textStatus + ", " + errorThrown);
				showAlert("An error occurred while processing the request.");
				$('#downloadLink').show().css('display', 'inline-block');
			},
			complete: function () {
				$("#generatefile").prop("disabled", false).text("Generate Export File");
			}
		});
	});
	
  
	function showAlert(message) {
		var alertBox = document.querySelector('.alert-container');
		var alertMessage = document.getElementById('alert-message');
	  
		alertMessage.textContent = message;
		alertBox.style.visibility = 'visible';
	  }
	function blinkElement(selector) {
		var element = $(selector);
		if (element.length > 0) {
		  // Start the blinking animation
		  startBlinking(element);
		}
	  }
	  
	  function startBlinking(element) {
		// Set the blink interval
		blinkInterval = setInterval(function() {
		  element.fadeOut(500, function() {
			$(this).fadeIn(500);
		  });
		}, 1000); // Adjust the interval as needed
	  
		// Schedule the stopBlinking function after 10 seconds
		blinkTimeout = setTimeout(function() {
		  stopBlinking(element);
		}, 10000); // 10 seconds (10000 milliseconds)
	  }

	  function stopBlinking(element) {
		// Clear the interval and timeout, and show the element
		clearInterval(blinkInterval);
		clearTimeout(blinkTimeout);
		element.stop().show();
	  }   
   
	// Add event listeners for drag and drop functionality
	$("#dropzone")
	  .on("dragover", function (e) {
		e.preventDefault();
		$(this).addClass("dragover");
	  })
	  .on("dragleave", function (e) {
		e.preventDefault();
		$(this).removeClass("dragover");
	  })
	  .on("drop", function (e) {
		e.preventDefault();
		$(this).removeClass("dragover");
  
		// Retrieve the dropped file
		var files = e.originalEvent.dataTransfer.files;
  
		// Check if any file is dropped
		if (files.length > 0) {
		  // Assign the dropped file to the file input
		  $("#importFile")[0].files = files;
		}
	  });
  
	$("#confpassword").on("keyup", function () {
	  var password = $("#password").val();
	  var confpassword = $("#confpassword").val();
	  if (password != confpassword) {
		$("#CheckPasswordMatch")
		  .html("Password does not match!")
		  .css("color", "red");
	  } else {
		$("#CheckPasswordMatch").html("Password match!").css("color", "green");
	  }
	});
  });
  