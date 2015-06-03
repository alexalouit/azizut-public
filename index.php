<?php
	// configuration
	include "config.php";

	// path
	$path = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	// check url is /link
	if(substr($_SERVER["REQUEST_URI"], 0, 5) === "/link") {
		if(isset($_POST["link"]) && !empty($_POST["link"])) {
			// it's a form call, shorten and return link as json/text
			header("Content-Type: application/json");
			print json_encode(shorten($_POST["link"]));
			exit;
		} else {
			$rawlink = substr($_SERVER["REQUEST_URI"], 6);
			if(!empty($rawlink)) {
				// it's a bookmarlet call, generate link in this page.
				$trigger = addslashes($rawlink);
			}
		}
	}

	function shorten($link) {
		if(!function_exists('json_decode')) {
			die("PECL json required.");
		}

		// prepare call
		$data = new \stdClass;
		$data->access = new \stdClass;
		$data->params = new \stdClass;
		$data->access->username = AZIZUT_USER;
		$data->access->password = AZIZUT_PASSWORD;
		$data->action = "insert";
		$data->params->url = $link;
//		$data->params->secure = true;
		$encoded_data = json_encode($data);


		// make call
		if(!function_exists('curl_init')) {
			// using php
			$opts = array('http' =>
				array(
					'method'  => 'POST',
					'header'  => 'Content-type: application/json\r\nContent-Length: ' . strlen($encoded_data) . '\r\n',
					'content' => $encoded_data,
					'timeout' => 4
				)
			);

			$context = stream_context_create($opts);
			$raw_response = file_get_contents(AZIZUT_SERVER, false, $context);

		} else {
			// using curl
			$buffer = curl_init();
			curl_setopt($buffer, CURLOPT_URL, AZIZUT_SERVER);
			curl_setopt($buffer, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($buffer, CURLOPT_TIMEOUT, 4);
			curl_setopt($buffer, CURLOPT_HEADER, 0);
			curl_setopt($buffer, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($buffer, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: " . strlen($encoded_data)));
			curl_setopt($buffer, CURLOPT_POST, 1);
			curl_setopt($buffer, CURLOPT_POSTFIELDS, $encoded_data);

			$raw_response = curl_exec($buffer);
			curl_close($buffer);
		}

		$response = json_decode($raw_response);

		// check response
		if(!empty($response->statusCode) && ($response->statusCode == 200 OR $response->statusCode == 202)) {
			// write to log

			// check directory is here
			if(!file_exists(LOGDIR)) {
				mkdir(LOGDIR, 0700);
				if(!file_exists(LOGDIR)) {
					error_log("Human intervention required. Unable to create " . LOGDIR . " (need rw from www-data)");
				}
			}

			// write to day log file
			$date_human_readable = date('Y-m-d H:i:s');
			$date_filename = date('Ymd');
			@error_log("[" . $date_human_readable . "] " . $_SERVER["REMOTE_ADDR"] . " from: " . $link . " to: " . $response->data->link . "\n", 3, LOGDIR . "log_" . $date_filename);

			return $response->data->link;
		} else {
			return FALSE;
		}
	}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="description" content="">
		<meta name="author" content="">
		<link rel="icon" href="/favicon.ico">
		<title>Azizut Shortener</title>
		<link href="/css/style.min.css" rel="stylesheet">
	</head>

	<body>

		<div class="site-wrapper">

			<div class="site-wrapper-inner">

				<div class="style-container">

					<div class="masthead clearfix">
						<div class="inner">
							<h4 class="style-heading"><a href="/">azizut</a></h4>
							<p class="lead">Raccourcisseur gratuit et <a href="https://github.com/alexalouit/azizut" target="_blank">OpenSource</a></p>
						</div>
					</div>

					<div class="inner style" id="input">
						<h3 class="style-heading"><span class="glyphicon glyphicon-link" aria-hidden="true"></span> Raccourcir une url</h3>
						<form class="col-lg-12" id="form" method="POST" action="/link/">
							<div class="input-group" style="text-align: center; margin: 0 auto;">
								<input class="form-control input-lg" name="link" id="url" placeholder="Entrer une url" type="text" value="<?php if(isset($trigger) && !empty($trigger)) { print $rawlink; } ?>" style="text-align: center;">
								<span class="input-group-btn"><button class="btn btn-lg btn-primary" id="short" type="button" disabled="disabled">OK</button></span>
							</div>
						</form>
					</div>

					<!--
					var d = document,
					w = window,
					e = w.getSelection,
					k = d.getSelection,
					l = d.location,
					e = encodeURIComponent;

					window.open('<?php echo $path; ?>link/' + e(l.href));
					-->

					<div class="inner style">
						<p><small>&agrave; glisser dans votre barre de favoris pour raccourcir en un click <span class="glyphicon glyphicon-hand-right" aria-hidden="true"></span> <a href="javascript:void%20function(){var%20o=document,n=window,e=n.getSelection,t=(o.getSelection,o.location),e=encodeURIComponent;window.open(%22<?php echo $path; ?>link/%22+e(t.href))}();">+azizut</a></small></p>
					</div>

					<h6 class="invalidUrl" style="display: none;"><span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span> Url invalide.</h6>
					<h6 class="validUrl" style="display: none;"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Url valide.</h6>

					<div style="text-align:center;"><span class="glyphicon glyphicon-repeat" id="working" aria-hidden="true" style="display: none;"></span></div>

					<div class="inner style" id="return" style="display: none;">
					</div>

					<div class="mastfoot">
						<div class="inner">
							<p><small>Con&ccedil;u pour <a href="http://www.zut.io/" target="_blank">zut.io</a> par <a href="https://twitter.com/alexalouit" target="_blank">@alexalouit</a></small></p>
							<p><small><a href="https://twitter.com/azi_zut" target="_blank">@azi_zut</a></small></p>
						</div>
					</div>

				</div>

			</div>

		</div>

		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
		<script type="text/javascript">
			// default value
			disableTrigger = true;

			// prevent hitting enter key
			$(document).ready(function() {
				$(window).keydown(function(event){
					if(event.keyCode == 13) {
						event.preventDefault();
						if(disableTrigger == false) {
							makeCall($('form#form').serialize());
						}
					}
				});
			});

			// valid url format
			$('#url').bind('input', function() { // use "bind('input', " instead "keyup(" for copy/paste from non-keyboard (like mobile, tablet or mouse)
				var url = document.getElementById("url").value;
				var pattern = new RegExp(
"^" +
	// protocol identifier
//	"(?:(?:https?|ftp)://)" +
//	"(?:(?:(?:https?|ftp):)?//)" +
	"(?:(?:(?:https?|ftp)://)?)" +
	// user:pass authentication
	"(?:\\S+(?::\\S*)?@)?" +
	"(?:" +
		// IP address exclusion
		// private & local networks
		"(?!(?:10|127)(?:\\.\\d{1,3}){3})" +
		"(?!(?:169\\.254|192\\.168)(?:\\.\\d{1,3}){2})" +
		"(?!172\\.(?:1[6-9]|2\\d|3[0-1])(?:\\.\\d{1,3}){2})" +
		// IP address dotted notation octets
		// excludes loopback network 0.0.0.0
		// excludes reserved space >= 224.0.0.0
		// excludes network & broacast addresses
		// (first & last IP address of each class)
		"(?:[1-9]\\d?|1\\d\\d|2[01]\\d|22[0-3])" +
		"(?:\\.(?:1?\\d{1,2}|2[0-4]\\d|25[0-5])){2}" +
		"(?:\\.(?:[1-9]\\d?|1\\d\\d|2[0-4]\\d|25[0-4]))" +
	"|" +
		// host name
		"(?:(?:[a-z\\u00a1-\\uffff0-9]-*)*[a-z\\u00a1-\\uffff0-9]+)" +
		// domain name
		"(?:\\.(?:[a-z\\u00a1-\\uffff0-9]-*)*[a-z\\u00a1-\\uffff0-9]+)*" +
		// TLD identifier
		"(?:\\.(?:[a-z\\u00a1-\\uffff]{2,}))" +
	")" +
	// port number
	"(?::\\d{2,5})?" +
	// resource path
	"(?:/\\S*)?" +
"$", "i"
);
				;
				if(pattern.test(url)) {
					// valid url
						disableTrigger = false;
					$('h6.invalidUrl').css({display:'none'});
					$('h6.validUrl').css({display:'initial'}).fadeIn('slow');
					$('button#short').prop('disabled', false);
				} else {
					// invalid url
						disableTrigger = true;
					$('h6.invalidUrl').css({display:'initial'}).fadeIn('slow');
					$('h6.validUrl').css({display:'none'});
					$('button#short').prop('disabled', true);
				}
			});

			// caller function
			makeCall = function(link) {
				$('#working').css({display:'initial'}).fadeIn('slow');
				$.ajax({
					type: "POST",
					url: '/link/',
					data: link,
					success: function(data) {
						if(typeof data.link != 'undefined') {
							$('button#short').prop('disabled', true);
							$('input#url').prop('disabled', true);
							disableTrigger = true;
							$('#working').css({display:'none'});
							$('#input').css({opacity:'0.5'});
							$('#return').html('<a href="' + data + '" target="_blank">' + data + '<br /><br /><img src="' + data + '.qr"></a>').fadeIn('slow');
						} else {
							$('#return').html('Une erreur est survenue. Veuillez ressayer.').fadeIn('slow');
						}
					},
					error : function() {
						$('#working').css({display:'none'});
						$('#return').html('Une erreur est survenue. Veuillez ressayer.').fadeIn('slow');
					}
				});
			};

<?php if(isset($trigger) && !empty($trigger)) { ?>
			// launch ajax call
			makeCall('link=<?php print $trigger; ?>');
<?php } ?>

			// form submit
			$("button#short").click(function() {
				makeCall($('form#form').serialize());
			});
		</script>
		<script type="text/javascript">
			var _paq = _paq || [];
			_paq.push(['trackPageView']);
			_paq.push(['enableLinkTracking']);
			(function() {
				var u="//stats.alouit-multimedia.com/piwik/";
				_paq.push(['setTrackerUrl', u+'piwik.php']);
				_paq.push(['setSiteId', 28]);
				var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
				g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
			})();
		</script>
		<noscript><p><img src="//stats.alouit-multimedia.com/piwik/piwik.php?idsite=28" style="border:0;" alt="" /></p></noscript>
		<script>
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
			ga('create', 'UA-61792207-1', 'auto');
			ga('send', 'pageview');
		</script>
	</body>
</html>
