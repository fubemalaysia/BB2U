<%@ page language="java" contentType="text/html; charset=UTF-8"
pageEncoding="UTF-8"%>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="UTF-8">
<link rel="stylesheet"
	href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
	integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u"
	crossorigin="anonymous">
<script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
<script src="js/webrtc_adaptor.js"></script>
<script
		src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>

<style>
video {
	width: 100%;
	max-width: 640px;
}
/* Space out content a bit */
body {
	padding-top: 20px;
	padding-bottom: 20px;
}

/* Everything but the jumbotron gets side spacing for mobile first views */
.header, .marketing, .footer {
	padding-right: 15px;
	padding-left: 15px;
}

/* Custom page header */
.header {
	padding-bottom: 20px;
	border-bottom: 1px solid #e5e5e5;
}
/* Make the masthead heading the same height as the navigation */
.header h3 {
	margin-top: 0;
	margin-bottom: 0;
	line-height: 40px;
}

/* Custom page footer */
.footer {
	padding-top: 19px;
	color: #777;
	border-top: 1px solid #e5e5e5;
}

/* Customize container */
@media ( min-width : 768px) {
	.container {
		max-width: 730px;
	}
}

.container-narrow>hr {
	margin: 30px 0;
}

/* Main marketing message and sign up button */
.jumbotron {
	text-align: center;
	border-bottom: 1px solid #e5e5e5;
}

/* Responsive: Portrait tablets and up */
@media screen and (min-width: 768px) {
	/* Remove the padding we set earlier */
	.header, .marketing, .footer {
		padding-right: 0;
		padding-left: 0;
	}
	/* Space out the masthead */
	.header {
		margin-bottom: 30px;
	}
	/* Remove the bottom border on the jumbotron for visual effect */
	.jumbotron {
		border-bottom: 0;
	}
}
</style>
</head>
<body>
	<div class="container">


		<div class="jumbotron">

			<p>
				<video id="localVideo" autoplay muted controls playsinline></video>
			</p>

			<p>
				<input type="text" class="form-control" value="stream1"
					id="streamName" placeholder="Type stream name">
			</p>
			<p>
				
				<button onclick="startPublishing()" class="btn btn-info" disabled
					id="start_publish_button">Start Publishing</button>
				<button onclick="stopPublishing()" class="btn btn-info" disabled
					id="stop_publish_button">Stop Publishing</button>
						
			</p>

						<span class="label label-success" id="broadcastingInfo" style="font-size:14px;display:none"
							style="display: none">Publishing</span>

		</div>

	</div>
</body>
<script>
	var token = "<%= request.getParameter("token") %>";

	var start_publish_button = document.getElementById("start_publish_button");
	var stop_publish_button = document.getElementById("stop_publish_button");
	
	var screen_share_checkbox = document.getElementById("screen_share_checkbox");
	var install_extension_link = document.getElementById("install_chrome_extension_link");

	var streamNameBox = document.getElementById("streamName");
	
	var streamId;
	
	function getUrlParameter(sParam) {
	    var sPageURL = decodeURIComponent(window.location.search.substring(1)),
	        sURLVariables = sPageURL.split('&'),
	        sParameterName,
	        i;

	    for (i = 0; i < sURLVariables.length; i++) {
	        sParameterName = sURLVariables[i].split('=');

	        if (sParameterName[0] === sParam) {
	            return sParameterName[1] === undefined ? true : sParameterName[1];
	        }
	    }
	};
	
	var name = getUrlParameter("name");
	if(name !== "undefined")
	{
		streamNameBox.value = name;
	}
	

	function startPublishing() {
		streamId = streamNameBox.value;
		webRTCAdaptor.publish(streamId, token);
	}

	function stopPublishing() {
		webRTCAdaptor.stop(streamId);
	}
	
	function enableDesktopCapture(enable) {
		if (enable == true) {
			webRTCAdaptor.switchDesktopCapture(streamId);
		}
		else {
			webRTCAdaptor.switchVideoCapture(streamId);
		}
	}
	
    function startAnimation() {

        $("#broadcastingInfo").fadeIn(800, function () {
          $("#broadcastingInfo").fadeOut(800, function () {
        	var state = webRTCAdaptor.signallingState(streamId);
            if (state != null && state != "closed") {
            	var iceState = webRTCAdaptor.iceConnectionState(streamId);
            	if (iceState != null && iceState != "failed" && iceState != "disconnected") {
              		startAnimation();
            	}
            }
          });
        });

      }

	var pc_config = null;

	var sdpConstraints = {
		OfferToReceiveAudio : false,
		OfferToReceiveVideo : false

	};
	
	var mediaConstraints = {
		video : true,
		audio : true
	};

	var appName = location.pathname.substring(0, location.pathname.lastIndexOf("/")+1);
	var path =  location.hostname + ":" + location.port + appName + "websocket";
	var websocketURL =  "ws://" + path;
	
	if (location.protocol.startsWith("https")) {
		websocketURL = "wss://" + path;
	}
	
	
	var webRTCAdaptor = new WebRTCAdaptor({
		websocket_url : websocketURL,
		mediaConstraints : mediaConstraints,
		peerconnection_config : pc_config,
		sdp_constraints : sdpConstraints,
		localVideoId : "localVideo",
		debug:true,
		callback : function(info, obj) {
			if (info == "initialized") {
				console.log("initialized");
				start_publish_button.disabled = false;
				stop_publish_button.disabled = true;
			} else if (info == "publish_started") {
				//stream is being published
				console.log("publish started");
				start_publish_button.disabled = true;
				stop_publish_button.disabled = false;
				startAnimation();
			} else if (info == "publish_finished") {
				//stream is being finished
				console.log("publish finished");
				start_publish_button.disabled = false;
				stop_publish_button.disabled = true;
			}
			else if (info == "screen_share_extension_available") {
				screen_share_checkbox.disabled = false;
				console.log("screen share extension available");
				install_extension_link.style.display = "none";
			}
			else if (info == "screen_share_stopped") {
				console.log("screen share stopped");
			}
			else if (info == "closed") {
				//console.log("Connection closed");
				if (typeof obj != "undefined") {
					console.log("Connecton closed: " + JSON.stringify(obj));
				}
			}
			else if (info == "pong") {
				//ping/pong message are sent to and received from server to make the connection alive all the time
				//It's especially useful when load balancer or firewalls close the websocket connection due to inactivity
			}
			else if (info == "refreshConnection") {
				startPublishing();
			}
			else if (info == "updated_stats") {
				//obj is the PeerStats which has fields
				 //averageOutgoingBitrate - kbits/sec
				//currentOutgoingBitrate - kbits/sec
				console.log("Average outgoing bitrate " + obj.averageOutgoingBitrate + " kbits/sec"
						+ " Current outgoing bitrate: " + obj.currentOutgoingBitrate + " kbits/sec");
				 
			}
		},
		callbackError : function(error, message) {
			//some of the possible errors, NotFoundError, SecurityError,PermissionDeniedError
            
			console.log("error callback: " +  JSON.stringify(error));
			var errorMessage = JSON.stringify(error);
			if (typeof message != "undefined") {
				errorMessage = message;
			}
			var errorMessage = JSON.stringify(error);
			if (error.indexOf("NotFoundError") != -1) {
				errorMessage = "Camera or Mic are not found or not allowed in your device";
			}
			else if (error.indexOf("NotReadableError") != -1 || error.indexOf("TrackStartError") != -1) {
				errorMessage = "Camera or Mic is being used by some other process that does not let read the devices";
			}
			else if(error.indexOf("OverconstrainedError") != -1 || error.indexOf("ConstraintNotSatisfiedError") != -1) {
				errorMessage = "There is no device found that fits your video and audio constraints. You may change video and audio constraints"
			}
			else if (error.indexOf("NotAllowedError") != -1 || error.indexOf("PermissionDeniedError") != -1) {
				errorMessage = "You are not allowed to access camera and mic.";
			}
			else if (error.indexOf("TypeError") != -1) {
				errorMessage = "Video/Audio is required";
			}
		
			alert(errorMessage);
		}
	});
</script>
</html>
